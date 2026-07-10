<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClaimController extends Controller
{
    /**
     * Centralized relationship tree reflecting the new normalized architecture:
     * InsuranceApplication -> DamageReport -> Claim
     */
    private function claimRelations(): array
    {
        return [
            'damageReport',
            'damageReport.insuranceApplication',
            'damageReport.insuranceApplication.farm.farmerProfile.user',
            'damageReport.insuranceApplication.season',
        ];
    }

    /**
     * Farmer Mobile/Web: View own claims dynamically filtered by farmer profile
     */
    public function myClaims($user_id)
    {
        $user = User::with('farmerProfile')->findOrFail($user_id);

        if (!$user->farmerProfile) {
            return response()->json([
                'message' => 'Farmer profile not found.'
            ], 404);
        }

        // Traverses the new deep relations down to the farmer_profile_id
        $claims = Claim::with($this->claimRelations())
            ->whereHas('damageReport.insuranceApplication.farm', function ($query) use ($user) {
                $query->where('farmer_profile_id', $user->farmerProfile->id);
            })
            ->latest()
            ->get();

        return response()->json($claims);
    }

    /**
     * MAO Panel: View all claims for dashboard monitoring
     */
 /**
     * MAO Panel: View all claims for dashboard monitoring
     * Dynamically handles 'current' vs 'previous' crop cycle seasons
     */
    public function index(Request $request)
    {
        // Default to showing 'current' seasons if no type parameter is supplied
        $seasonType = $request->query('season_type', 'current');

        $claims = Claim::with($this->claimRelations())
            ->whereHas('damageReport.insuranceApplication.season', function ($query) use ($seasonType) {
                if ($seasonType === 'current') {
                    // Decoupled logic: Fetch seasons that are either open for applications 
                    // OR closed for applications but still active for crop monitoring/claims
                    $query->whereIn('status', ['application_open', 'application_closed']);
                } else {
                    // Fetch completed/archived seasons
                    $query->where('status', 'completed');
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
     * MAO Action: Batch or single assignment marking claims as transmitted to PCIC
     */
    public function submitToPcic($id)
    {
        $claim = Claim::findOrFail($id);

        $claim->update([
            'status' => 'submitted_to_pcic',
            'submitted_to_pcic_at' => now(),
        ]);

        return response()->json([
            'message' => 'Claim status successfully marked as submitted to PCIC.',
            'claim' => $claim->load($this->claimRelations()),
        ]);
    }

    /**
     * MAO Action: Process and save final insurance adjustments sent by PCIC
     */
    public function updatePcicResult(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'result'         => 'required|in:approved,rejected',
            'claim_amount'   => 'nullable|numeric|min:0',
            'claim_schedule' => 'nullable|date',
            'claim_venue'    => 'nullable|string|max:255',
            'pcic_remarks'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $claim = Claim::findOrFail($id);

        if ($request->result === 'approved') {
            // Strict enforcement on approvals
            $approvalValidator = Validator::make($request->all(), [
                'claim_amount'   => 'required|numeric|min:0',
                'claim_schedule' => 'required|date',
                'claim_venue'    => 'required|string|max:255',
            ]);

            if ($approvalValidator->fails()) {
                return response()->json([
                    'message' => 'Approval fields are missing.',
                    'errors'  => $approvalValidator->errors()
                ], 422);
            }

            $claim->update([
                'claim_amount'   => $request->claim_amount,
                'claim_schedule' => $request->claim_schedule,
                'claim_venue'    => $request->claim_venue,
                'pcic_remarks'   => $request->pcic_remarks,
                'pcic_status'    => 'approved',
                'status'         => 'ready_for_claiming',
            ]);
        } else {
            // Reject updates and clear any accidental payload items
            $claim->update([
                'claim_amount'   => null,
                'claim_schedule' => null,
                'claim_venue'    => null,
                'pcic_remarks'   => $request->pcic_remarks,
                'pcic_status'    => 'rejected',
                'status'         => 'rejected',
            ]);
        }

        return response()->json([
            'message' => 'PCIC evaluation values applied successfully.',
            'claim'   => $claim->load($this->claimRelations()),
        ]);
    }

    /**
     * MAO Action: Payout release validation flag
     */
    public function markClaimed($id)
    {
        $claim = Claim::findOrFail($id);

        $claim->update([
            'status' => 'claimed',
        ]);

        return response()->json([
            'message' => 'Claim status resolved as fully claimed.',
            'claim'   => $claim->load($this->claimRelations()),
        ]);
    }

    /**
     * Fallback Resource Update: Direct column adjustments
     */
    public function update(Request $request, $id)
    {
        $claim = Claim::findOrFail($id);

        $validated = $request->validate([
            'status'       => 'nullable|string',
            'pcic_status'  => 'nullable|string',
            'claim_amount' => 'nullable|numeric|min:0',
            'pcic_remarks' => 'nullable|string',
        ]);

        $claim->update($validated);

        return response()->json([
            'message' => 'Resource records updated successfully.',
            'claim'   => $claim->load($this->claimRelations()),
        ]);
    }
}