<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Mail\ClaimReadyForClaimingMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

use Exception;

class ClaimController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Centralized relationship tree reflecting the normalized architecture:
     * InsuranceApplication -> DamageReport -> Claim
     */
    private function claimRelations(): array
    {
        return [
            'damageReport',
            'damageReport.insuranceApplication',
            'damageReport.insuranceApplication.farm',
            'damageReport.insuranceApplication.farm.farmerProfile',
            'damageReport.insuranceApplication.farm.farmerProfile.user',
            'damageReport.insuranceApplication.farm.farmerProfile.user.barangay',
            'damageReport.insuranceApplication.season',
        ];
    }

    /**
     * Farmer Mobile/Web: View own claims dynamically filtered by farmer profile
     */
    public function myClaims(Request $request, $user_id)
    {
        $seasonType = $request->query('season_type', 'current');

        $claims = Claim::with($this->claimRelations())
            ->whereHas('damageReport.insuranceApplication.farm.farmerProfile', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })
            ->whereHas('damageReport.insuranceApplication.season', function ($query) use ($seasonType) {
                if ($seasonType === 'current') {
                    $query->whereIn('status', ['application_open', 'application_closed']);
                } else {
                    $query->whereNotIn('status', ['application_open', 'application_closed']);
                }
            })
            ->latest()
            ->get();

        return response()->json($claims);
    }

    /**
     * MAO Panel: View all claims for dashboard monitoring
     */
    public function index(Request $request)
    {
        $seasonType = $request->query('season_type', 'current');

        $claims = Claim::with($this->claimRelations())
            ->has('damageReport.insuranceApplication.season')
            ->whereHas('damageReport.insuranceApplication.season', function ($query) use ($seasonType) {
                if ($seasonType === 'current') {
                    $query->whereIn('status', ['application_open', 'application_closed']);
                } else {
                    $query->whereNotIn('status', ['application_open', 'application_closed']);
                }
            })
            ->latest()
            ->get();

        return response()->json($claims);
    }

    /**
     * View specific details for a single claim instance
     */
    public function show($id)
    {
        $claim = Claim::with($this->claimRelations())->findOrFail($id);
        return response()->json($claim);
    }

    /**
     * Farmer Mobile: File CAS-02 form
     */
    public function fileIndemnityClaim(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'crop_stage_at_loss'           => 'required|string|max:255',
            'area_damaged'                 => 'required|numeric|min:0',
            'degree_of_damage'             => 'required|numeric|min:0|max:100',
            'expected_harvest_date'        => 'required|string|max:255',
            'cost_land_preparation'        => 'required|numeric|min:0',
            'cost_seedling_transplanting'  => 'required|numeric|min:0',
            'cost_seeds'                   => 'required|numeric|min:0',
            'cost_fertilizer'              => 'required|numeric|min:0',
            'cost_chemicals'               => 'required|numeric|min:0',
            'cost_others'                  => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $claim = Claim::with($this->claimRelations())->find($id);

        if (!$claim) {
            $claim = Claim::with($this->claimRelations())
                ->where('damage_report_id', $id)
                ->where('status', 'pending_filing')
                ->first();
        }

        if (!$claim) {
            return response()->json([
                'message' => "No claim record found matching ID {$id}."
            ], 404);
        }

        $user = $request->user()?->load('farmerProfile');

        if (!$user || !$user->farmerProfile) {
            return response()->json(['message' => 'Farmer profile not found.'], 404);
        }

        $claimFarmerProfileId = $claim->damageReport
            ?->insuranceApplication
            ?->farm
            ?->farmer_profile_id;

        if ($claimFarmerProfileId !== $user->farmerProfile->id) {
            return response()->json(['message' => 'You are not authorized to file this claim.'], 403);
        }

        if ($claim->degree_of_damage !== null) {
            return response()->json(['message' => 'This claim has already been filed.'], 422);
        }

        if ($claim->status !== 'pending_filing') {
            return response()->json(['message' => 'This claim is not ready to be filed yet.'], 422);
        }

        $costLandPrep   = (float) $request->input('cost_land_preparation', 0);
        $costTransplant = (float) $request->input('cost_seedling_transplanting', 0);
        $costSeeds      = (float) $request->input('cost_seeds', 0);
        $costFertilizer = (float) $request->input('cost_fertilizer', 0);
        $costChemicals  = (float) $request->input('cost_chemicals', 0);
        $costOthers     = (float) $request->input('cost_others', 0);

        $totalProductionCost = $costLandPrep + $costTransplant + $costSeeds + $costFertilizer + $costChemicals + $costOthers;

        $claim->update([
            'crop_stage_at_loss'          => $request->input('crop_stage_at_loss'),
            'area_damaged'                => (float) $request->input('area_damaged'),
            'degree_of_damage'            => (float) $request->input('degree_of_damage'),
            'expected_harvest_date'       => $request->input('expected_harvest_date'),
            'cost_land_preparation'       => $costLandPrep,
            'cost_seedling_transplanting' => $costTransplant,
            'cost_seeds'                   => $costSeeds,
            'cost_fertilizer'              => $costFertilizer,
            'cost_chemicals'               => $costChemicals,
            'cost_others'                 => $costOthers,
            'total_production_cost'       => $totalProductionCost,
            'claim_filed_date'            => now()->toDateString(),
            'status'                      => 'under_mao_review',
        ]);

        return response()->json([
            'message' => 'Indemnity claim filed successfully.',
            'claim'   => $claim->fresh($this->claimRelations()),
        ]);
    }

    /**
     * MAO Action: Generating/Downloading PDF for physical submission
     */
    public function downloadCas02Pdf($id)
    {
        $claim = Claim::with($this->claimRelations())->findOrFail($id);

        if ($claim->status === 'under_mao_review') {
            $claim->update([
                'status'               => 'in_pcic_processing',
                'submitted_to_pcic_at' => now(),
            ]);
        }

        $pdf = Pdf::loadView('pdf.cas02', compact('claim'));
        return $pdf->download("CAS-02_Claim_{$id}.pdf");
    }

    /**
     * MAO Action: Process and save final insurance results from PCIC.
     */
    public function updatePcicResult(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'result'       => 'required|in:approved,rejected',
            'pcic_remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $claim = Claim::findOrFail($id);

        if ($request->result === 'approved') {
            $claim->update([
                'pcic_remarks' => $request->pcic_remarks,
                'pcic_status'  => 'approved',
                'status'       => 'ready_for_claiming',
            ]);

            $this->notifyClaimReadyForClaiming($claim);
        } else {
            $claim->update([
                'claim_schedule' => null,
                'claim_venue'    => null,
                'pcic_remarks'   => $request->pcic_remarks,
                'pcic_status'    => 'rejected',
                'status'         => 'pcic_rejected',
            ]);
        }

        return response()->json([
            'message' => 'PCIC evaluation values applied successfully.',
            'claim'   => $claim->load($this->claimRelations()),
        ]);
    }

    /**
     * MAO Panel: Bulk-assign ONE claiming date + venue to MULTIPLE claims
     */
    public function bulkSetSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'claim_ids'      => 'required|array|min:1',
            'claim_ids.*'    => 'integer|exists:claims,id',
            'claim_schedule' => 'required|date',
            'claim_venue'    => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $eligibleIds = Claim::whereIn('id', $request->claim_ids)
            ->whereIn('status', ['ready_for_claiming', 'in_pcic_processing'])
            ->pluck('id');

        $skippedIds = collect($request->claim_ids)->diff($eligibleIds)->values();

        if ($eligibleIds->isEmpty()) {
            return response()->json([
                'message' => 'None of the selected claims are eligible for scheduling.',
                'skipped_claim_ids' => $skippedIds,
            ], 422);
        }

        Claim::whereIn('id', $eligibleIds)->update([
            'claim_schedule' => $request->claim_schedule,
            'claim_venue'    => $request->claim_venue,
            'status'         => 'ready_for_claiming',
        ]);

        $updatedClaims = Claim::with($this->claimRelations())
            ->whereIn('id', $eligibleIds)
            ->get();

        foreach ($updatedClaims as $claim) {
            $this->notifyClaimReadyForClaiming($claim);
        }

        return response()->json([
            'message'           => count($eligibleIds) . ' claim(s) scheduled successfully.',
            'updated_claim_ids' => $eligibleIds,
            'skipped_claim_ids' => $skippedIds,
            'claims'            => $updatedClaims,
        ]);
    }

    /**
     * MAO Action: Payout release confirmation
     */
    public function markClaimed($id)
    {
        $claim = Claim::findOrFail($id);

        $claim->update([
            'claimed_at' => now(),
            'status'     => 'claimed',
        ]);

        return response()->json([
            'message' => 'Claim status resolved as fully claimed.',
            'claim'   => $claim->load($this->claimRelations()),
        ]);
    }

    /**
     * Fallback Resource Update
     */
    public function update(Request $request, $id)
    {
        $claim = Claim::findOrFail($id);

        $validated = $request->validate([
            'status'       => 'nullable|string',
            'pcic_status'  => 'nullable|string',
            'pcic_remarks' => 'nullable|string',
        ]);

        $claim->update($validated);

        return response()->json([
            'message' => 'Resource records updated successfully.',
            'claim'   => $claim->load($this->claimRelations()),
        ]);
    }

    /**
     * Internal Helper: Sends multi-channel notifications (In-App, Push, Email, SMS)
     */
  private function notifyClaimReadyForClaiming(mixed $claim): void
{
    // 1. Re-fetch as proper Eloquent App\Models\Claim instance if passed as stdClass or ID
    if (!$claim instanceof \App\Models\Claim) {
        $claimId = is_object($claim) ? $claim->id : $claim;
        $claim = \App\Models\Claim::with($this->claimRelations())->find($claimId);
    }

    if (!$claim) {
        Log::warning("[Claim Notification] Skipped: Claim model could not be resolved.");
        return;
    }

    // 2. Resolve User & Farmer Profile through relationship chain
    $claim->loadMissing($this->claimRelations());
    $farmerProfile = $claim->damageReport?->insuranceApplication?->farm?->farmerProfile;
    $user = $farmerProfile?->user;

    if (!$user) {
        Log::warning("[Claim Notification] Skipped for Claim #{$claim->id}: User profile missing.");
        return;
    }

    $title = "Claim Ready for Claiming";
    $venue = $claim->claim_venue ?? 'MAO Office';
    $schedule = $claim->claim_schedule ?? 'To be announced';
    $messageText = "Your indemnity claim (#{$claim->id}) is ready for claiming at {$venue}. Schedule: {$schedule}.";

    // A. In-App Database Record & Push Notification
    try {
        NotificationService::send($user->id, $title, $messageText);
        Log::info("[Claim Notification] Push/In-App triggered for User ID #{$user->id}");
    } catch (\Throwable $e) {
        Log::error("[Claim Notification] Push error on Claim #{$claim->id}: " . $e->getMessage());
    }

    // B. SMS Notification (Handles both User & FarmerProfile phone fields)
    $rawPhone = $user->phone_number 
        ?? $user->mobile_number 
        ?? $farmerProfile?->phone_number 
        ?? $farmerProfile?->mobile_number;

    if (!empty($rawPhone)) {
        // Strip dashes, spaces, and formatting symbols
        $formattedPhone = preg_replace('/[^0-9]/', '', $rawPhone);

        try {
            $smsResult = $this->smsService->sendMessage($formattedPhone, "AgriSure: {$messageText}");
            Log::info("[Claim Notification] SMS dispatched to {$formattedPhone}");
        } catch (\Throwable $e) {
            Log::error("[Claim Notification] SMS error on Claim #{$claim->id}: " . $e->getMessage());
        }
    } else {
        Log::warning("[Claim Notification] SMS skipped for User ID #{$user->id}: No phone number found.");
    }

    // C. Email Notification
    if (!empty($user->email)) {
        try {
            Mail::to($user->email)->send(new ClaimReadyForClaimingMail($claim));
            Log::info("[Claim Notification] Email sent to {$user->email}");
        } catch (\Throwable $e) {
            Log::error("[Claim Notification] Email error on Claim #{$claim->id}: " . $e->getMessage());
        }
    } else {
        Log::info("[Claim Notification] Email skipped for User ID #{$user->id}: Email is empty.");
    }
}
}