<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\Claim;
use App\Models\InsuranceApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DamageReportController extends Controller
{
    /**
     * Centralized relationships tree matching the normalized architecture.
     * Traces farmer profiles and users properly through the Farm relation.
     */
    private function reportRelations(): array
    {
        return [
            'insuranceApplication.farm.farmerProfile.user',
            'insuranceApplication.season',
            'claim',
        ];
    }

    public function store(Request $request)
    {
        // 1. Automatically find the application if Flutter only sent farm_id
        if ($request->has('farm_id') && !$request->has('insurance_application_id')) {
            // FIX: Explicitly scope down to applications matching the current OPEN season
            $application = InsuranceApplication::where('farm_id', $request->farm_id)
                ->whereIn('status', ['submitted_to_mao', 'submitted_to_pcic', 'insured'])
                ->whereHas('season', function ($query) {
                    $query->where('status', 'open');
                })
                ->latest()
                ->first();

            if (!$application) {
                return response()->json([
                    'message' => 'No active insurance application found for this farm in the current open season. Cannot submit damage report.',
                ], 422);
            }
            
            // Inject it into the request parameters dynamically
            $request->merge(['insurance_application_id' => $application->id]);
        }

        // 2. Run validation (insurance_application_id is now safely populated)
        $validator = Validator::make($request->all(), [
            'insurance_application_id' => 'required|exists:insurance_applications,id',
            'damage_cause'             => 'required|in:Typhoon,Flood,Drought,Pest Infestation,Disease,Rat Damage,Other',
            'damage_date'              => 'required|date',
            'damage_image'             => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'report_latitude'          => 'required|numeric',
            'report_longitude'         => 'required|numeric',
            'client_uuid'              => 'nullable|uuid',
            'sync_source'              => 'nullable|in:online,offline',
            'captured_at'              => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->client_uuid) {
            $existingReport = DamageReport::where('client_uuid', $request->client_uuid)->first();
            if ($existingReport) {
                return response()->json([
                    'message'       => 'Damage report already synced.',
                    'damage_report' => $existingReport,
                ], 200);
            }
        }

        $application = InsuranceApplication::with('farm')->findOrFail($request->insurance_application_id);
        $farm = $application->farm;

        $distance = $this->calculateDistance(
            $farm->latitude,
            $farm->longitude,
            $request->report_latitude,
            $request->report_longitude
        );

        $isSuspicious = $distance > 100;
        $imagePath = $request->file('damage_image')->store('damage_reports', 'public');

        $report = DamageReport::create([
            'insurance_application_id' => $application->id,
            'farm_id'                  => $farm->id, // Safe fallback for dual schemas
            'damage_cause'             => $request->damage_cause,
            'damage_date'              => $request->damage_date,
            'damage_image_path'        => $imagePath,
            'report_latitude'          => $request->report_latitude,
            'report_longitude'         => $request->report_longitude,
            'distance_from_farm'       => $distance,
            'is_suspicious'            => $isSuspicious,
            'status'                   => 'submitted_to_mao',
            'client_uuid'              => $request->client_uuid,
            'sync_source'              => $request->sync_source ?? 'online',
            'captured_at'              => $request->captured_at,
        ]);

        return response()->json([
            'message' => $isSuspicious
                ? 'Damage report submitted, but marked as suspicious due to location mismatch.'
                : 'Damage report submitted successfully.',
            'damage_report' => $report,
        ], 201);
    }

    public function farmReports($farm_id)
    {
        // FIX: Ensure historical reports fetched for this farm layout are strictly bound to the active season context
        return DamageReport::whereHas('insuranceApplication', function ($query) use ($farm_id) {
            $query->where('farm_id', $farm_id)
                  ->whereHas('season', function ($sQuery) {
                      $sQuery->where('status', 'open');
                  });
        })
        ->with($this->reportRelations())
        ->latest()
        ->get();
    }

    public function index()
    {
        return DamageReport::with($this->reportRelations())
        ->latest()
        ->get();
    }

    public function show($id)
    {
        return DamageReport::with($this->reportRelations())->findOrFail($id);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:submitted_to_mao,validated_by_mao,rejected',
        ]);

        $report = DamageReport::findOrFail($id);
        $report->update(['status' => $request->status]);

        $claim = null;
        if ($request->status === 'validated_by_mao') {
            $claim = Claim::firstOrCreate(
                ['damage_report_id' => $report->id],
                [
                    'status'      => 'validated_by_mao',
                    'pcic_status' => 'pending',
                ]
            );
        }

        return response()->json([
            'message' => $request->status === 'validated_by_mao'
                ? 'Damage report validated and claim created.'
                : 'Damage report status updated successfully.',
            'damage_report' => $report->load($this->reportRelations()),
            'claim' => $claim,
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $lat1 = deg2rad((float) $lat1);
        $lon1 = deg2rad((float) $lon1);
        $lat2 = deg2rad((float) $lat2);
        $lon2 = deg2rad((float) $lon2);

        $latDifference = $lat2 - $lat1;
        $lonDifference = $lon2 - $lon1;

        $a = sin($latDifference / 2) * sin($latDifference / 2) +
            cos($lat1) * cos($lat2) *
            sin($lonDifference / 2) * sin($lonDifference / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }
}