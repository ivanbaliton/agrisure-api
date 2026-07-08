<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\User;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    /**
     * Farmer: View own claims
     */
    public function myClaims($user_id)
    {
        $user = User::with('farmerProfile')->findOrFail($user_id);

        if (!$user->farmerProfile) {
            return response()->json([
                'message' => 'Farmer profile not found.'
            ], 404);
        }

        $claims = Claim::with([
            'damageReport',
            'damageReport.farm',
            'damageReport.season', // <--- Added for frontend filtering
        ])
            ->whereHas('damageReport.farm', function ($query) use ($user) {
                $query->where(
                    'farmer_profile_id',
                    $user->farmerProfile->id
                );
            })
            ->latest()
            ->get();

        return response()->json($claims);
    }

    /**
     * MAO: View all claims
     */
    public function index()
    {
        return Claim::with([
            'damageReport',
            'damageReport.farm',
            'damageReport.farm.farmerProfile',
            'damageReport.farm.farmerProfile.user',
            'damageReport.season', // <--- Added for frontend filtering
        ])
            ->latest()
            ->get();
    }

    /**
     * View single claim details
     */
    public function show($id)
    {
        $claim = Claim::with([
            'damageReport',
            'damageReport.farm',
            'damageReport.farm.farmerProfile',
            'damageReport.farm.farmerProfile.user',
            'damageReport.season', // <--- Added for frontend filtering
        ])->findOrFail($id);

        return response()->json($claim);
    }

    /**
     * MAO: Mark claim as submitted to PCIC
     */
    public function submitToPcic($id)
    {
        $claim = Claim::findOrFail($id);

        $claim->update([
            'status' => 'submitted_to_pcic',
            'submitted_to_pcic_at' => now(),
        ]);

        return response()->json([
            'message' => 'Claim marked as submitted to PCIC.',
            'claim' => $claim->load([
                'damageReport.farm.farmerProfile.user',
                'damageReport.season',
            ]),
        ]);
    }

    /**
     * MAO: Encode PCIC result
     */
    /**
     * MAO: Encode PCIC result
     */
    public function updatePcicResult(Request $request, $id)
    {
        $request->validate([
            'result' => 'required|in:approved,rejected',
            'claim_amount' => 'nullable|numeric|min:0',
            'claim_schedule' => 'nullable|date',
            'claim_venue' => 'nullable|string|max:255',
            'pcic_remarks' => 'nullable|string',
        ]);

        $claim = Claim::findOrFail($id);

        if ($request->result === 'approved') {
            $request->validate([
                'claim_amount' => 'required|numeric|min:0',
                'claim_schedule' => 'required|date',
                'claim_venue' => 'required|string|max:255',
            ]);

            $claim->update([
                'claim_amount' => $request->claim_amount,
                'claim_schedule' => $request->claim_schedule,
                'claim_venue' => $request->claim_venue,
                'pcic_remarks' => $request->pcic_remarks,
                'pcic_status' => 'approved', // <--- FIX: Save the approved flag
                'status' => 'ready_for_claiming',
            ]);
        } else {
            $claim->update([
                'claim_amount' => null,
                'claim_schedule' => null,
                'claim_venue' => null,
                'pcic_remarks' => $request->pcic_remarks,
                'pcic_status' => 'rejected', // <--- FIX: Save the rejected flag
                'status' => 'rejected', // <--- Make sure this matches your migration enum!
            ]);
        }

        return response()->json([
            'message' => 'PCIC result saved successfully.',
            'claim' => $claim->load([
                'damageReport.farm.farmerProfile.user',
                'damageReport.season',
            ]),
        ]);
    }

    /**
     * MAO: Mark claim as claimed / released
     */
    public function markClaimed($id)
    {
        $claim = Claim::findOrFail($id);

        $claim->update([
            'status' => 'claimed',
        ]);

        return response()->json([
            'message' => 'Claim marked as claimed successfully.',
            'claim' => $claim->load([
                'damageReport.farm.farmerProfile.user',
                'damageReport.season',
            ]),
        ]);
    }

    /**
     * General update, useful for inspection date or manual corrections
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'inspection_date' => 'nullable|date',
            'claim_amount' => 'nullable|numeric|min:0',
            'claim_schedule' => 'nullable|date',
            'claim_venue' => 'nullable|string|max:255',
            'pcic_status' => 'nullable|in:pending,approved,rejected',
            'pcic_remarks' => 'nullable|string',
            'status' => 'required|in:validated_by_mao,submitted_to_pcic,pcic_approved,pcic_rejected,ready_for_claiming,claimed',
        ]);

        $claim = Claim::findOrFail($id);

        $claim->update([
            'inspection_date' => $request->inspection_date,
            'claim_amount' => $request->claim_amount,
            'claim_schedule' => $request->claim_schedule,
            'claim_venue' => $request->claim_venue,
            'pcic_status' => $request->pcic_status ?? $claim->pcic_status,
            'pcic_remarks' => $request->pcic_remarks,
            'status' => $request->status,
        ]);

        $claim->damageReport?->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Claim updated successfully.',
            'claim' => $claim->load([
                'damageReport.farm.farmerProfile.user',
                'damageReport.season',
            ]),
        ]);
    }
}