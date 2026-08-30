<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Mail\ClaimReadyForClaimingMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ClaimController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Centralized relationship tree.
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
     * Farmer Mobile/Web: View own claims.
     */
    public function myClaims(Request $request, $user_id)
    {
        $seasonType = $request->query('season_type', 'current');

        $claims = Claim::with($this->claimRelations())
            ->whereHas(
                'damageReport.insuranceApplication.farm.farmerProfile',
                function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }
            )
            ->whereHas(
                'damageReport.insuranceApplication.season',
                function ($query) use ($seasonType) {
                    if ($seasonType === 'current') {
                        $query->whereIn(
                            'status',
                            ['application_open', 'application_closed']
                        );
                    } else {
                        $query->whereNotIn(
                            'status',
                            ['application_open', 'application_closed']
                        );
                    }
                }
            )
            ->latest()
            ->get();

        return response()->json($claims);
    }

    /**
     * MAO Panel: View all claims.
     */
    public function index(Request $request)
    {
        $seasonType = $request->query('season_type', 'current');

        $claims = Claim::with($this->claimRelations())
            ->has('damageReport.insuranceApplication.season')
            ->whereHas(
                'damageReport.insuranceApplication.season',
                function ($query) use ($seasonType) {
                    if ($seasonType === 'current') {
                        $query->whereIn(
                            'status',
                            ['application_open', 'application_closed']
                        );
                    } else {
                        $query->whereNotIn(
                            'status',
                            ['application_open', 'application_closed']
                        );
                    }
                }
            )
            ->latest()
            ->get();

        return response()->json($claims);
    }

    /**
     * View specific claim.
     */
    public function show($id)
    {
        $claim = Claim::with($this->claimRelations())
            ->findOrFail($id);

        return response()->json($claim);
    }

    /**
     * Farmer Mobile: File CAS-02 indemnity claim.
     *
     * Status:
     * pending_filing -> under_mao_review
     */
    public function fileIndemnityClaim(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'crop_stage_at_loss'          => 'required|string|max:255',
            'area_damaged'                => 'required|numeric|min:0',
            'degree_of_damage'            => 'required|numeric|min:0|max:100',
            'expected_harvest_date'       => 'required|string|max:255',
            'cost_land_preparation'       => 'required|numeric|min:0',
            'cost_seedling_transplanting' => 'required|numeric|min:0',
            'cost_seeds'                  => 'required|numeric|min:0',
            'cost_fertilizer'             => 'required|numeric|min:0',
            'cost_chemicals'              => 'required|numeric|min:0',
            'cost_others'                 => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $claim = Claim::with($this->claimRelations())
            ->find($id);

        /*
         * Fallback:
         * Sometimes the ID supplied by the farmer may be
         * the damage report ID.
         */
        if (!$claim) {
            $claim = Claim::with($this->claimRelations())
                ->where('damage_report_id', $id)
                ->where('status', 'pending_filing')
                ->first();
        }

        if (!$claim) {
            return response()->json([
                'message' => "No claim record found matching ID {$id}.",
            ], 404);
        }

        $user = $request->user()?->load('farmerProfile');

        if (!$user || !$user->farmerProfile) {
            return response()->json([
                'message' => 'Farmer profile not found.',
            ], 404);
        }

        $claimFarmerProfileId = $claim->damageReport
            ?->insuranceApplication
            ?->farm
            ?->farmer_profile_id;

        if ($claimFarmerProfileId !== $user->farmerProfile->id) {
            return response()->json([
                'message' => 'You are not authorized to file this claim.',
            ], 403);
        }

        if ($claim->degree_of_damage !== null) {
            return response()->json([
                'message' => 'This claim has already been filed.',
            ], 422);
        }

        if ($claim->status !== 'pending_filing') {
            return response()->json([
                'message' => 'This claim is not ready to be filed yet.',
            ], 422);
        }

        $costLandPrep   = (float) $request->input(
            'cost_land_preparation',
            0
        );

        $costTransplant = (float) $request->input(
            'cost_seedling_transplanting',
            0
        );

        $costSeeds = (float) $request->input(
            'cost_seeds',
            0
        );

        $costFertilizer = (float) $request->input(
            'cost_fertilizer',
            0
        );

        $costChemicals = (float) $request->input(
            'cost_chemicals',
            0
        );

        $costOthers = (float) $request->input(
            'cost_others',
            0
        );

        $totalProductionCost =
            $costLandPrep +
            $costTransplant +
            $costSeeds +
            $costFertilizer +
            $costChemicals +
            $costOthers;

        $claim->update([
            'crop_stage_at_loss'          => $request->input('crop_stage_at_loss'),
            'area_damaged'                => (float) $request->input('area_damaged'),
            'degree_of_damage'            => (float) $request->input('degree_of_damage'),
            'expected_harvest_date'       => $request->input('expected_harvest_date'),
            'cost_land_preparation'       => $costLandPrep,
            'cost_seedling_transplanting' => $costTransplant,
            'cost_seeds'                  => $costSeeds,
            'cost_fertilizer'             => $costFertilizer,
            'cost_chemicals'              => $costChemicals,
            'cost_others'                 => $costOthers,
            'total_production_cost'       => $totalProductionCost,
            'claim_filed_date'            => now()->toDateString(),
            'status'                      => 'under_mao_review',
        ]);

        /*
         * EVERY STATUS CHANGE:
         * In-App + Push
         */
        $this->notifyClaimStatusChanged(
            $claim,
            'under_mao_review'
        );

        return response()->json([
            'message' => 'Indemnity claim filed successfully.',
            'claim'   => $claim->fresh($this->claimRelations()),
        ]);
    }

    /**
     * MAO: Generate/download CAS-02 PDF.
     *
     * Status:
     * under_mao_review -> in_pcic_processing
     */
    public function downloadCas02Pdf($id)
    {
        $claim = Claim::with($this->claimRelations())
            ->findOrFail($id);

        if ($claim->status === 'under_mao_review') {

            $claim->update([
                'status'               => 'in_pcic_processing',
                'submitted_to_pcic_at' => now(),
            ]);

            /*
             * EVERY STATUS CHANGE:
             * In-App + Push
             */
            $this->notifyClaimStatusChanged(
                $claim,
                'in_pcic_processing'
            );
        }

        $pdf = Pdf::loadView(
            'pdf.cas02',
            compact('claim')
        );

        return $pdf->download(
            "CAS-02_Claim_{$id}.pdf"
        );
    }

    /**
     * MAO: Save final PCIC result.
     *
     * Approved:
     * -> ready_for_claiming
     *
     * Rejected:
     * -> pcic_rejected
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
                'errors'  => $validator->errors(),
            ], 422);
        }

        $claim = Claim::with($this->claimRelations())
            ->findOrFail($id);

        if ($request->result === 'approved') {

            $claim->update([
                'pcic_remarks' => $request->pcic_remarks,
                'pcic_status'  => 'approved',
                'status'       => 'ready_for_claiming',
            ]);

            /*
             * READY FOR CLAIMING:
             *
             * In-App
             * Push
             * SMS
             * Email
             */
            $this->notifyClaimStatusChanged(
                $claim,
                'ready_for_claiming'
            );

        } else {

            $claim->update([
                'claim_schedule' => null,
                'claim_venue'    => null,
                'pcic_remarks'   => $request->pcic_remarks,
                'pcic_status'    => 'rejected',
                'status'         => 'pcic_rejected',
            ]);

            /*
             * Rejected:
             *
             * In-App
             * Push
             *
             * NO SMS
             * NO EMAIL
             */
            $this->notifyClaimStatusChanged(
                $claim,
                'pcic_rejected'
            );
        }

        return response()->json([
            'message' => 'PCIC evaluation values applied successfully.',
            'claim'   => $claim->load(
                $this->claimRelations()
            ),
        ]);
    }

    /**
     * MAO: Bulk assign claiming schedule.
     *
     * Status:
     * ready_for_claiming
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
                'errors'  => $validator->errors(),
            ], 422);
        }

        $eligibleIds = Claim::whereIn(
            'id',
            $request->claim_ids
        )
            ->whereIn(
                'status',
                [
                    'ready_for_claiming',
                    'in_pcic_processing',
                ]
            )
            ->pluck('id');

        $skippedIds = collect(
            $request->claim_ids
        )
            ->diff($eligibleIds)
            ->values();

        if ($eligibleIds->isEmpty()) {
            return response()->json([
                'message' => 'None of the selected claims are eligible for scheduling.',
                'skipped_claim_ids' => $skippedIds,
            ], 422);
        }

        Claim::whereIn('id', $eligibleIds)
            ->update([
                'claim_schedule' => $request->claim_schedule,
                'claim_venue'    => $request->claim_venue,
                'status'         => 'ready_for_claiming',
            ]);

        $updatedClaims = Claim::with(
            $this->claimRelations()
        )
            ->whereIn('id', $eligibleIds)
            ->get();

        foreach ($updatedClaims as $claim) {

            /*
             * If it was already ready_for_claiming,
             * this will still send the notification again.
             *
             * Since this action changes the schedule,
             * this is useful because the farmer needs
             * the updated date and venue.
             */
            $this->notifyClaimStatusChanged(
                $claim,
                'ready_for_claiming'
            );
        }

        return response()->json([
            'message'           => count($eligibleIds) . ' claim(s) scheduled successfully.',
            'updated_claim_ids' => $eligibleIds,
            'skipped_claim_ids' => $skippedIds,
            'claims'            => $updatedClaims,
        ]);
    }

    /**
     * MAO: Confirm claim payout/release.
     *
     * Status:
     * ready_for_claiming -> claimed
     */
    public function markClaimed($id)
    {
        $claim = Claim::with($this->claimRelations())
            ->findOrFail($id);

        $claim->update([
            'claimed_at' => now(),
            'status'     => 'claimed',
        ]);

        /*
         * EVERY STATUS CHANGE:
         * In-App + Push
         *
         * NO SMS
         * NO EMAIL
         */
        $this->notifyClaimStatusChanged(
            $claim,
            'claimed'
        );

        return response()->json([
            'message' => 'Claim status resolved as fully claimed.',
            'claim'   => $claim->load(
                $this->claimRelations()
            ),
        ]);
    }

    /**
     * Generic/Fallback Resource Update.
     *
     * If status changes through this endpoint,
     * the farmer will also receive In-App + Push.
     *
     * If the new status is ready_for_claiming,
     * SMS + Email will also be sent.
     */
    public function update(Request $request, $id)
    {
        $claim = Claim::with(
            $this->claimRelations()
        )->findOrFail($id);

        $validated = $request->validate([
            'status'       => 'nullable|string',
            'pcic_status'  => 'nullable|string',
            'pcic_remarks' => 'nullable|string',
        ]);

        $oldStatus = $claim->status;

        $claim->update($validated);

        /*
         * Only notify when the actual claim status
         * changed.
         */
        if (
            array_key_exists('status', $validated) &&
            $validated['status'] !== null &&
            $validated['status'] !== $oldStatus
        ) {
            $this->notifyClaimStatusChanged(
                $claim,
                $validated['status']
            );
        }

        return response()->json([
            'message' => 'Resource records updated successfully.',
            'claim'   => $claim->load(
                $this->claimRelations()
            ),
        ]);
    }

    /**
     * ==========================================================
     * CENTRAL CLAIM NOTIFICATION METHOD
     * ==========================================================
     *
     * EVERY STATUS:
     *      In-App + Push
     *
     * ready_for_claiming:
     *      In-App + Push
     *      + SMS
     *      + Email
     */
    private function notifyClaimStatusChanged(
        Claim $claim,
        string $status
    ): void {

        /*
         * Reload the complete relationship tree.
         */
        $claim->loadMissing(
            $this->claimRelations()
        );

        /*
         * Resolve farmer profile.
         */
        $farmerProfile =
            $claim->damageReport
                ?->insuranceApplication
                ?->farm
                ?->farmerProfile;

        /*
         * Resolve actual User model.
         */
        $user = $farmerProfile?->user;

        if (!$user) {
            Log::warning(
                "[Claim Notification] User not found for Claim #{$claim->id}"
            );

            return;
        }

        /*
         * Build status-specific notification.
         */
        $notification = $this->buildClaimNotification(
            $claim,
            $status
        );

        $title = $notification['title'];
        $message = $notification['message'];

        /*
         * ======================================================
         * 1. IN-APP + PUSH
         * ======================================================
         *
         * NotificationService::send() is your existing
         * notification service responsible for creating
         * the in-app notification and sending FCM push.
         */
        try {

            NotificationService::send(
                $user->id,
                $title,
                $message
            );

            Log::info(
                "[Claim Notification] In-App + Push sent",
                [
                    'claim_id' => $claim->id,
                    'user_id'  => $user->id,
                    'status'   => $status,
                ]
            );

        } catch (\Throwable $e) {

            Log::error(
                "[Claim Notification] In-App/Push failed",
                [
                    'claim_id' => $claim->id,
                    'user_id'  => $user->id,
                    'status'   => $status,
                    'error'    => $e->getMessage(),
                ]
            );
        }

        /*
         * ======================================================
         * 2. SMS + EMAIL ONLY FOR READY FOR CLAIMING
         * ======================================================
         */
        if ($status !== 'ready_for_claiming') {
            return;
        }

        /*
         * ======================================================
         * SMS
         * ======================================================
         */
        $rawPhone =
            $user->phone_number
            ?? $user->mobile_number
            ?? $farmerProfile?->phone_number
            ?? $farmerProfile?->mobile_number;

        if (!empty($rawPhone)) {

            /*
             * Normalize Philippine phone number.
             *
             * Examples:
             * 09171234567
             * 0917-123-4567
             * +639171234567
             */
            $formattedPhone = preg_replace(
                '/[^0-9+]/',
                '',
                $rawPhone
            );

            /*
             * Convert 09XXXXXXXXX to +639XXXXXXXXX
             * for consistency.
             */
            if (
                str_starts_with(
                    $formattedPhone,
                    '09'
                )
            ) {
                $formattedPhone =
                    '+63' .
                    substr(
                        $formattedPhone,
                        1
                    );
            }

            try {

                $smsMessage =
                    "AgriSure: Your indemnity claim "
                    . "#{$claim->id} is ready for claiming. "
                    . "Venue: "
                    . ($claim->claim_venue ?? 'MAO Office')
                    . ". Schedule: "
                    . ($claim->claim_schedule ?? 'To be announced')
                    . ".";

                $smsResult =
                    $this->smsService->sendMessage(
                        $formattedPhone,
                        $smsMessage
                    );

                Log::info(
                    "[Claim Notification] SMS sent successfully",
                    [
                        'claim_id' => $claim->id,
                        'user_id'  => $user->id,
                        'phone'    => $formattedPhone,
                        'response' => $smsResult,
                    ]
                );

            } catch (\Throwable $e) {

                /*
                 * SMS failure does NOT stop
                 * the notification process.
                 *
                 * Example:
                 * - Semaphore sender name not approved
                 * - No credits
                 * - Invalid number
                 * - API failure
                 */
                Log::error(
                    "[Claim Notification] SMS failed",
                    [
                        'claim_id' => $claim->id,
                        'user_id'  => $user->id,
                        'phone'    => $formattedPhone,
                        'error'    => $e->getMessage(),
                    ]
                );
            }

        } else {

            Log::warning(
                "[Claim Notification] SMS skipped: "
                . "No phone number for User #{$user->id}",
                [
                    'claim_id' => $claim->id,
                ]
            );
        }

        /*
         * ======================================================
         * EMAIL
         * ======================================================
         */
        if (!empty($user->email)) {

            try {

                Mail::to($user->email)
                    ->send(
                        new ClaimReadyForClaimingMail($claim)
                    );

                Log::info(
                    "[Claim Notification] Email sent successfully",
                    [
                        'claim_id' => $claim->id,
                        'user_id'  => $user->id,
                        'email'    => $user->email,
                    ]
                );

            } catch (\Throwable $e) {

                /*
                 * Email failure does NOT stop
                 * the notification process.
                 */
                Log::error(
                    "[Claim Notification] Email failed",
                    [
                        'claim_id' => $claim->id,
                        'user_id'  => $user->id,
                        'email'    => $user->email,
                        'error'    => $e->getMessage(),
                    ]
                );
            }

        } else {

            Log::info(
                "[Claim Notification] Email skipped: "
                . "No email for User #{$user->id}",
                [
                    'claim_id' => $claim->id,
                ]
            );
        }
    }

    /**
     * Build notification title and message
     * based on claim status.
     */
    private function buildClaimNotification(
        Claim $claim,
        string $status
    ): array {

        $venue =
            $claim->claim_venue
            ?? 'MAO Office';

        $schedule =
            $claim->claim_schedule
            ?? 'To be announced';

        switch ($status) {

            case 'under_mao_review':

                return [
                    'title' =>
                        'Indemnity Claim Under Review',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} is now under "
                        . "MAO review.",
                ];

            case 'in_pcic_processing':

                return [
                    'title' =>
                        'Claim Submitted to PCIC',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} has been submitted "
                        . "to PCIC for processing.",
                ];

            case 'ready_for_claiming':

                return [
                    'title' =>
                        'Claim Ready for Claiming',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} is ready for claiming. "
                        . "Venue: {$venue}. "
                        . "Schedule: {$schedule}.",
                ];

            case 'pcic_rejected':

                return [
                    'title' =>
                        'Indemnity Claim Rejected',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} was rejected by PCIC."
                        . (
                            !empty($claim->pcic_remarks)
                                ? " Remarks: {$claim->pcic_remarks}"
                                : ''
                        ),
                ];

            case 'claimed':

                return [
                    'title' =>
                        'Indemnity Claim Released',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} has been marked as claimed.",
                ];

            case 'pending_filing':

                return [
                    'title' =>
                        'Indemnity Claim Pending Filing',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} is waiting for the "
                        . "required claim form.",
                ];

            default:

                /*
                 * Generic fallback for any future
                 * claim statuses you add.
                 */
                $readableStatus =
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $status
                        )
                    );

                return [
                    'title' =>
                        'Claim Status Updated',

                    'message' =>
                        "Your indemnity claim "
                        . "#{$claim->id} status has been updated "
                        . "to {$readableStatus}.",
                ];
        }
    }
}

