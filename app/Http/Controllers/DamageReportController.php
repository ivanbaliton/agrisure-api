<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\Claim;
use App\Models\InsuranceApplication;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DamageReportController extends Controller
{
    /**
     * Centralized relationships tree matching the normalized architecture.
     */
    private function reportRelations(): array
    {
        return [
            'insuranceApplication.farm.farmerProfile.user',
            'insuranceApplication.season',
            'claim',
        ];
    }

    /**
     * Submit a damage report from the farmer mobile app.
     */
    public function store(Request $request)
    {
        // Automatically find the application if Flutter only sent farm_id
        if (
            $request->has('farm_id') &&
            !$request->has('insurance_application_id')
        ) {
            $application = InsuranceApplication::where(
                'farm_id',
                $request->farm_id
            )
                ->whereIn('status', [
                    'submitted_to_mao',
                    'submitted_to_pcic',
                    'insured'
                ])
                ->whereHas('season', function ($query) {
                    $query->whereIn('status', [
                        'application_open',
                        'application_closed'
                    ]);
                })
                ->latest()
                ->first();

            if (!$application) {
                return response()->json([
                    'message' =>
                        'No active insurance application found for this farm in the current operational season cycle. Cannot submit damage report.',
                ], 422);
            }

            $request->merge([
                'insurance_application_id' => $application->id
            ]);
        }

        // Validation
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

        // Prevent duplicate offline synchronization
        if ($request->client_uuid) {

            $existingReport = DamageReport::where(
                'client_uuid',
                $request->client_uuid
            )->first();

            if ($existingReport) {
                return response()->json([
                    'message'       => 'Damage report already synced.',
                    'damage_report' => $existingReport,
                ], 200);
            }
        }

        // Get application and farm
        $application = InsuranceApplication::with('farm')
            ->findOrFail($request->insurance_application_id);

        $farm = $application->farm;

        // Calculate distance from registered farm location
        $distance = $this->calculateDistance(
            $farm->latitude,
            $farm->longitude,
            $request->report_latitude,
            $request->report_longitude
        );

        $isSuspicious = $distance > 100;

        // Store image
        $imagePath = $request
            ->file('damage_image')
            ->store('damage_reports', 'public');

        // Create damage report
        $report = DamageReport::create([
            'insurance_application_id' => $application->id,
            'farm_id'                  => $farm->id,
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

        /*
         * ==========================================================
         * NOTIFICATION
         * ==========================================================
         *
         * Notify the farmer that the damage report was submitted.
         */
        try {

            $farmer = $application
                ->farm
                ->farmerProfile
                ->user ?? null;

            if ($farmer) {

                NotificationService::send(
                    $farmer->id,
                    'Damage Report Submitted',
                    'Your damage report has been submitted successfully and is now awaiting review by the Municipal Agriculture Office.'
                );
            }

        } catch (\Throwable $e) {

            \Log::error(
                'Damage report notification failed.',
                [
                    'damage_report_id' => $report->id,
                    'error'            => $e->getMessage(),
                ]
            );
        }

        return response()->json([
            'message' => $isSuspicious
                ? 'Damage report submitted, but marked as suspicious due to location mismatch.'
                : 'Damage report submitted successfully.',

            'damage_report' => $report,
        ], 201);
    }

    /**
     * Farmer mobile app:
     * View damage reports for a farm.
     */
    public function farmReports($farm_id)
    {
        return DamageReport::whereHas(
            'insuranceApplication',
            function ($query) use ($farm_id) {

                $query->where('farm_id', $farm_id)
                    ->whereHas('season', function ($sQuery) {

                        $sQuery->whereIn('status', [
                            'application_open',
                            'application_closed'
                        ]);

                    });
            }
        )
            ->with($this->reportRelations())
            ->latest()
            ->get();
    }

    /**
     * MAO Panel:
     * View all damage reports.
     */
    public function index(Request $request)
    {
        $seasonType = $request->query(
            'season_type',
            'current'
        );

        return DamageReport::with(
            $this->reportRelations()
        )
            ->whereHas(
                'insuranceApplication.season',
                function ($query) use ($seasonType) {

                    if ($seasonType === 'current') {

                        $query->whereIn('status', [
                            'application_open',
                            'application_closed'
                        ]);

                    } else {

                        $query->where(
                            'status',
                            'completed'
                        );
                    }
                }
            )
            ->latest()
            ->get();
    }

    /**
     * Show a single damage report.
     */
    public function show($id)
    {
        return DamageReport::with(
            $this->reportRelations()
        )->findOrFail($id);
    }

    /**
     * MAO:
     * Update damage report status.
     *
     * Sends:
     * - In-app notification
     * - FCM push notification
     *
     * When validated_by_mao:
     * - Creates the claim record.
     */
    public function updateStatus(
        Request $request,
        $id
    ) {

        $request->validate([
            'status' => 'required|in:submitted_to_mao,validated_by_mao,rejected',
        ]);

        /*
         * Find report with farmer relationship.
         */
        $report = DamageReport::with([
            'insuranceApplication.farm.farmerProfile.user'
        ])->findOrFail($id);

        /*
         * Update status.
         */
        $report->update([
            'status' => $request->status
        ]);

        /*
         * ==========================================================
         * CREATE CLAIM WHEN VALIDATED
         * ==========================================================
         */

        $claim = null;

        if (
            $request->status === 'validated_by_mao'
        ) {

            $claim = Claim::firstOrCreate(
                [
                    'damage_report_id' => $report->id
                ],
                [
                    'status'    => 'pending_filing',
                    'pcic_status' => 'pending',
                ]
            );
        }

        /*
         * ==========================================================
         * FIND FARMER
         * ==========================================================
         */

        $farmer = null;

        if (
            $report->insuranceApplication &&
            $report->insuranceApplication->farm &&
            $report->insuranceApplication->farm->farmerProfile
        ) {

            $farmer =
                $report
                    ->insuranceApplication
                    ->farm
                    ->farmerProfile
                    ->user;
        }

        /*
         * ==========================================================
         * SEND IN-APP + PUSH NOTIFICATION
         * ==========================================================
         */

        if ($farmer) {

            $title = '';
            $message = '';

            switch ($request->status) {

                /*
                 * --------------------------------------------------
                 * SUBMITTED TO MAO
                 * --------------------------------------------------
                 */

                case 'submitted_to_mao':

                    $title =
                        'Damage Report Submitted';

                    $message =
                        'Your damage report has been submitted and is awaiting review by the Municipal Agriculture Office.';

                    break;


                /*
                 * --------------------------------------------------
                 * VALIDATED BY MAO
                 * --------------------------------------------------
                 */

                case 'validated_by_mao':

                    $title =
                        'Damage Report Validated';

                    $message =
                        'Your damage report has been validated by the Municipal Agriculture Office. You may now file your indemnity claim.';

                    break;


                /*
                 * --------------------------------------------------
                 * REJECTED
                 * --------------------------------------------------
                 */

                case 'rejected':

                    $title =
                        'Damage Report Rejected';

                    $message =
                        'Your damage report has been rejected by the Municipal Agriculture Office. Please check the damage report details for more information.';

                    break;
            }

            /*
             * Send through the centralized notification service.
             *
             * This creates:
             *
             * 1. In-app notification
             * 2. FCM push notification
             */
            $notificationResult = NotificationService::send(
                $farmer->id,
                $title,
                $message
            );

            \Log::info(
                'Damage report notification processed.',
                [
                    'damage_report_id' => $report->id,
                    'farmer_id'        => $farmer->id,
                    'status'           => $request->status,
                    'in_app_sent'      => $notificationResult['in_app_sent'] ?? false,
                    'push_sent'        => $notificationResult['push_sent'] ?? false,
                ]
            );
        } else {

            \Log::warning(
                'Damage report notification skipped: farmer could not be resolved.',
                [
                    'damage_report_id' => $report->id,
                ]
            );
        }

        /*
         * ==========================================================
         * RESPONSE
         * ==========================================================
         */

        return response()->json([

            'message' =>
                $request->status === 'validated_by_mao'
                    ? 'Damage report validated. Claim initialized awaiting farmer indemnity filing.'
                    : 'Damage report status updated successfully.',

            'damage_report' =>
                $report->load(
                    $this->reportRelations()
                ),

            'claim' => $claim,

        ]);
    }

    /**
     * Calculate distance between two coordinates.
     */
    private function calculateDistance(
        $lat1,
        $lon1,
        $lat2,
        $lon2
    ) {

        $earthRadius = 6371000;

        $lat1 = deg2rad((float) $lat1);
        $lon1 = deg2rad((float) $lon1);

        $lat2 = deg2rad((float) $lat2);
        $lon2 = deg2rad((float) $lon2);

        $latDifference = $lat2 - $lat1;
        $lonDifference = $lon2 - $lon1;

        $a =
            sin($latDifference / 2) *
            sin($latDifference / 2) +

            cos($lat1) *
            cos($lat2) *
            sin($lonDifference / 2) *
            sin($lonDifference / 2);

        $c =
            2 *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );

        return round(
            $earthRadius * $c,
            2
        );
    }
}

