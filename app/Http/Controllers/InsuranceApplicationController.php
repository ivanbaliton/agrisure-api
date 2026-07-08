<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\InsuranceApplication;
use App\Models\InsuranceSeason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InsuranceApplicationController extends Controller
{
    private function getOrCreateCurrentSeason()
    {
        $season = InsuranceSeason::where('status', 'open')
            ->latest()
            ->first();

        if (!$season) {
            $season = InsuranceSeason::create([
                'season_name' => 'Default Season ' . now()->year,
                'deadline_date' => null,
                'status' => 'open',
                'is_default' => true,
            ]);
        }

        return $season;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farm_id' => 'required|exists:farms,id',
            'insured_area' => 'required|numeric|min:0.01',

            'civil_status' => 'required|string|max:50',
            'beneficiary_name' => 'required|string|max:255',
            'spouse_name' => 'nullable|string|max:255',
            'parent_guardian_name' => 'nullable|string|max:255',

            'variety' => 'required|string|max:255',
            'farm_type' => 'required|in:Irrigated,Rainfed',

            'sowing_date' => 'nullable|date',
            'transplanting_date' => 'nullable|date',

            'north_boundary' => 'required|string|max:255',
            'east_boundary' => 'required|string|max:255',
            'west_boundary' => 'required|string|max:255',
            'south_boundary' => 'required|string|max:255',

            'is_land_owner' => 'required|boolean',
            'tenure_status' => 'required|in:Owner Cultivator,Tenant,Leaseholder,Others',

            'signature' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',

            'payment_proof' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'gcash_reference_number' => 'nullable|string|max:100',

            'client_uuid' => 'nullable|uuid',
            'sync_source' => 'nullable|in:online,offline',
            'captured_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->client_uuid) {
            $existingApplication = InsuranceApplication::where(
                'client_uuid',
                $request->client_uuid
            )->first();

            if ($existingApplication) {
                return response()->json([
                    'message' => 'Insurance application already synced.',
                    'application' => $existingApplication,
                ], 200);
            }
        }

        $farm = Farm::findOrFail($request->farm_id);
        $season = $this->getOrCreateCurrentSeason();

        if ($season->status !== 'open') {
            return response()->json([
                'message' => 'This insurance season is already closed.',
            ], 422);
        }

        if (
            $season->deadline_date &&
            now()->toDateString() > $season->deadline_date->toDateString()
        ) {
            $season->update([
                'status' => 'closed',
            ]);

            $season = InsuranceSeason::create([
                'season_name' => 'Default Season ' . now()->year,
                'deadline_date' => null,
                'status' => 'open',
                'is_default' => true,
            ]);
        }

        $existingFarmApplication = InsuranceApplication::where('farm_id', $farm->id)
            ->where('insurance_season_id', $season->id)
            ->whereIn('status', [
                'submitted_to_mao',
                'submitted_to_pcic',
                'insured',
            ])
            ->first();

        if ($existingFarmApplication) {
            return response()->json([
                'message' => 'This farm already has an active application for this season.',
            ], 409);
        }

        $registeredFarmArea = (float) $farm->farm_area;
        $insuredArea = (float) $request->insured_area;

        if ($insuredArea > $registeredFarmArea) {
            return response()->json([
                'message' => 'Insured area cannot exceed registered farm area.',
                'registered_farm_area' => $registeredFarmArea,
                'insured_area' => $insuredArea,
            ], 422);
        }

        $freeCoverageLimit = 3.00;
        $premiumRatePerHectare = 1000;

        $usedFreeArea = InsuranceApplication::whereHas('farm', function ($query) use ($farm) {
            $query->where(
                'farmer_profile_id',
                $farm->farmer_profile_id
            );
        })
            ->where('insurance_season_id', $season->id)
            ->whereIn('status', [
                'submitted_to_mao',
                'submitted_to_pcic',
                'insured',
            ])
            ->sum('covered_free_area');

        $remainingFreeArea = max(0, $freeCoverageLimit - $usedFreeArea);
        $coveredFreeArea = min($insuredArea, $remainingFreeArea);
        $excessArea = max(0, $insuredArea - $remainingFreeArea);
        $premiumAmount = $excessArea * $premiumRatePerHectare;
        $requiresPayment = $excessArea > 0;
        $freeCoverageAfter = max(0, $remainingFreeArea - $coveredFreeArea);

        if (
            $requiresPayment &&
            (
                !$request->hasFile('payment_proof') ||
                !$request->filled('gcash_reference_number')
            )
        ) {
            return response()->json([
                'message' => 'GCash reference number and payment proof are required.',
                'requires_payment' => true,
                'free_coverage_limit' => $freeCoverageLimit,
                'used_free_area' => (float) $usedFreeArea,
                'remaining_free_area' => $remainingFreeArea,
                'registered_farm_area' => $registeredFarmArea,
                'insured_area' => $insuredArea,
                'covered_free_area' => $coveredFreeArea,
                'excess_area' => $excessArea,
                'premium_amount' => $premiumAmount,
                'free_coverage_after' => $freeCoverageAfter,
            ], 422);
        }

        $signaturePath = null;

        if ($request->hasFile('signature')) {
            $signaturePath = $request->file('signature')
                ->store('signatures', 'public');
        }

        $paymentProofPath = null;

        if ($request->hasFile('payment_proof')) {
            $paymentProofPath = $request->file('payment_proof')
                ->store('payment_proofs', 'public');
        }

        $application = InsuranceApplication::create([
            'farm_id' => $farm->id,
            'insurance_season_id' => $season->id,

            'civil_status' => $request->civil_status,
            'beneficiary_name' => $request->beneficiary_name,
            'spouse_name' => $request->spouse_name,
            'parent_guardian_name' => $request->parent_guardian_name,

            'variety' => $request->variety,
            'farm_type' => $request->farm_type,

            'sowing_date' => $request->sowing_date,
            'transplanting_date' => $request->transplanting_date,

            'north_boundary' => $request->north_boundary,
            'east_boundary' => $request->east_boundary,
            'west_boundary' => $request->west_boundary,
            'south_boundary' => $request->south_boundary,

            'is_land_owner' => $request->is_land_owner,
            'tenure_status' => $request->tenure_status,

            'application_date' => now()->toDateString(),
            'status' => 'submitted_to_mao',
            'signature_path' => $signaturePath,

            'insured_area' => $insuredArea,
            'free_coverage_before' => $remainingFreeArea,
            'covered_free_area' => $coveredFreeArea,
            'excess_area' => $excessArea,
            'free_coverage_after' => $freeCoverageAfter,
            'premium_amount' => $premiumAmount,

            'payment_status' => $requiresPayment
                ? 'pending_verification'
                : 'not_required',

            'payment_method' => $requiresPayment
                ? 'gcash'
                : null,

            'payment_proof_path' => $paymentProofPath,

            'gcash_reference_number' => $requiresPayment
                ? $request->gcash_reference_number
                : null,

            'payment_submitted_at' => $requiresPayment
                ? now()
                : null,

            'client_uuid' => $request->client_uuid,
            'sync_source' => $request->sync_source ?? 'online',
            'captured_at' => $request->captured_at,
        ]);

        $farm->update([
            'insurance_status' => 'submitted_to_mao',
        ]);

        return response()->json([
            'message' => $requiresPayment
                ? 'Insurance application submitted. Payment proof is pending MAO verification.'
                : 'Insurance application submitted to MAO successfully.',
            'application' => $application,
            'payment' => [
                'requires_payment' => $requiresPayment,
                'free_coverage_limit' => $freeCoverageLimit,
                'used_free_area' => (float) $usedFreeArea,
                'remaining_free_area' => $remainingFreeArea,
                'registered_farm_area' => $registeredFarmArea,
                'insured_area' => $insuredArea,
                'covered_free_area' => $coveredFreeArea,
                'excess_area' => $excessArea,
                'free_coverage_after' => $freeCoverageAfter,
                'premium_amount' => $premiumAmount,
                'payment_status' => $application->payment_status,
                'payment_method' => $application->payment_method,
                'gcash_reference_number' => $application->gcash_reference_number,
            ],
            'season' => $season,
        ], 201);
    }

    public function index()
    {
        return InsuranceApplication::with([
            'season',
            'farm',
            'farm.farmerProfile',
            'farm.farmerProfile.user',
        ])
            ->latest()
            ->get();
    }

    public function show($id)
    {
        return InsuranceApplication::with([
            'season',
            'farm',
            'farm.farmerProfile',
            'farm.farmerProfile.user',
        ])->findOrFail($id);
    }



    public function submitToPcic($id)
    {
        $application = InsuranceApplication::findOrFail($id);

        if ($application->payment_status === 'pending_verification') {
            return response()->json([
                'message' => 'Payment must be verified before submitting to PCIC.',
            ], 422);
        }

        if ($application->payment_status === 'rejected') {
            return response()->json([
                'message' => 'Payment proof was rejected. Application cannot be submitted to PCIC.',
            ], 422);
        }

        $application->update([
            'status' => 'submitted_to_pcic',
        ]);

        $application->farm->update([
            'insurance_status' => 'submitted_to_pcic',
        ]);

        return response()->json([
            'message' => 'Application marked as submitted to PCIC.',
            'application' => $application,
        ]);
    }

    public function approve($id)
    {
        $application = InsuranceApplication::findOrFail($id);

        if ($application->payment_status === 'pending_verification') {
            return response()->json([
                'message' => 'Payment must be verified before approving this application.',
            ], 422);
        }

        if ($application->payment_status === 'rejected') {
            return response()->json([
                'message' => 'Payment proof was rejected. Application cannot be approved.',
            ], 422);
        }

        $application->update([
            'status' => 'insured',
        ]);

        $application->farm->update([
            'insurance_status' => 'insured',
        ]);

        return response()->json([
            'message' => 'Insurance application approved.',
            'application' => $application,
        ]);
    }

    public function reject(Request $request, $id)
    {
        $application = InsuranceApplication::findOrFail($id);

        $application->update([
            'status' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        $application->farm->update([
            'insurance_status' => 'rejected',
        ]);

        return response()->json([
            'message' => 'Insurance application rejected.',
            'application' => $application,
        ]);
    }

    public function verifyPayment($id)
    {
        $application = InsuranceApplication::findOrFail($id);

        if ($application->payment_status === 'not_required') {
            return response()->json([
                'message' => 'Payment is not required for this application.',
            ], 422);
        }

        $application->update([
            'payment_status' => 'verified',
        ]);

        return response()->json([
            'message' => 'Payment verified successfully. Application may now proceed to PCIC.',
            'application' => $application,
        ]);
    }

    public function rejectPayment(Request $request, $id)
    {
        $application = InsuranceApplication::findOrFail($id);

        if ($application->payment_status === 'not_required') {
            return response()->json([
                'message' => 'Payment is not required for this application.',
            ], 422);
        }

        $application->update([
            'payment_status' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'message' => 'Payment proof rejected.',
            'application' => $application,
        ]);
    }

    public function freeCoverage($user_id)
    {
        $freeCoverageLimit = 3.00;
        $season = $this->getOrCreateCurrentSeason();

        $usedFreeArea = InsuranceApplication::whereHas(
            'farm.farmerProfile',
            function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            }
        )
            ->where('insurance_season_id', $season->id)
            ->whereIn('status', [
                'submitted_to_mao',
                'submitted_to_pcic',
                'insured',
            ])
            ->sum('covered_free_area');

        $remainingFreeArea = max(
            0,
            $freeCoverageLimit - $usedFreeArea
        );

        return response()->json([
            'season' => $season,
            'free_coverage_limit' => $freeCoverageLimit,
            'used_free_area' => (float) $usedFreeArea,
            'remaining_free_area' => $remainingFreeArea,
        ]);
    }

    public function farmHistory($farm_id)
{
    return InsuranceApplication::with([
        'season',
        'farm',
        'farm.farmerProfile',
        'farm.farmerProfile.user',
    ])
        ->where('farm_id', $farm_id)
        ->latest()
        ->get();
}


public function history()
{
    $currentSeason = InsuranceSeason::where('status', 'open')
        ->where('is_default', false)
        ->latest()
        ->first();

    return InsuranceApplication::with([
        'farm',
        'farm.farmerProfile',
        'farm.farmerProfile.user',
        'season',
    ])
        ->when($currentSeason, function ($query) use ($currentSeason) {
            $query->where('insurance_season_id', '!=', $currentSeason->id);
        })
        ->latest()
        ->get();
}

public function approveForPcic($id)
{
    $application = InsuranceApplication::findOrFail($id);

    $application->update([
        'status' => 'approved_for_pcic',
    ]);

    return response()->json([
        'message' => 'Application approved for PCIC submission.',
        'application' => $application,
    ]);
}

public function needsRevision($id)
{
    $application = InsuranceApplication::findOrFail($id);

    $application->update([
        'status' => 'needs_revision',
    ]);

    return response()->json([
        'message' => 'Application flagged for document revision.',
        'application' => $application,
    ]);
}



}