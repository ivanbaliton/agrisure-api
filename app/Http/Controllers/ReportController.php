<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Models\Farm;
use App\Models\InsuranceApplication;
use App\Models\DamageReport;
use App\Models\Claim;
use App\Models\DistributionList;
use App\Models\InventorySupply;
use App\Models\Barangay;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Dashboard Filters Blueprint
     */
    private function reportFilters(Request $request)
    {
        return [
            'season_id'   => $request->season_id,
            'barangay_id' => $request->barangay_id,
            'crop_type'   => $request->crop_type,
            'year'        => $request->year,
        ];
    }

    /**
     * Dashboard Overview
     */
    public function overview(Request $request)
    {
        $filters = $this->reportFilters($request);

        $applications = InsuranceApplication::query();
        $damageReports = DamageReport::query();
        $claims = Claim::query();
        $distribution = DistributionList::query();

        // Season Filter
        if ($filters['season_id']) {
            $applications->where('insurance_season_id', $filters['season_id']);
            $damageReports->whereHas('farm.insuranceApplication', function ($q) use ($filters) {
                $q->where('insurance_season_id', $filters['season_id']);
            });
            $claims->whereHas('damageReport.farm.insuranceApplication', function ($q) use ($filters) {
                $q->where('insurance_season_id', $filters['season_id']);
            });
            // distribution_lists has no direct seasonal tracking in schema, matching if pivot/relationship maps it
        }

        // Crop Filter
        if ($filters['crop_type']) {
            $applications->whereHas('farm', function ($q) use ($filters) {
                $q->where('crop_type', $filters['crop_type']);
            });
            $damageReports->whereHas('farm', function ($q) use ($filters) {
                $q->where('crop_type', $filters['crop_type']);
            });
            $claims->whereHas('damageReport.farm', function ($q) use ($filters) {
                $q->where('crop_type', $filters['crop_type']);
            });
        }

        // Barangay Filter (via users table mapping)
        if ($filters['barangay_id']) {
            $applications->whereHas('farm.farmerProfile.user', function ($q) use ($filters) {
                $q->where('barangay_id', $filters['barangay_id']);
            });
            $damageReports->whereHas('farm.farmerProfile.user', function ($q) use ($filters) {
                $q->where('barangay_id', $filters['barangay_id']);
            });
            $claims->whereHas('damageReport.farm.farmerProfile.user', function ($q) use ($filters) {
                $q->where('barangay_id', $filters['barangay_id']);
            });
            $distribution->whereHas('distributionLists', function ($q) use ($filters) {
                $q->where('barangay_id', $filters['barangay_id']);
            });
        }

        // Year Filter
        if ($filters['year']) {
            $applications->whereYear('created_at', $filters['year']);
            $damageReports->whereYear('created_at', $filters['year']);
            $claims->whereYear('created_at', $filters['year']);
            $distribution->whereYear('created_at', $filters['year']);
        }

        return response()->json([
            'summary' => [
                'total_farmers'          => FarmerProfile::count(),
                'total_farms'            => Farm::count(),
                'rice_farms'             => Farm::where('crop_type', 'Rice')->count(),
                'corn_farms'             => Farm::where('crop_type', 'Corn')->count(),
                'insurance_applications' => $applications->count(),
                'damage_reports'         => $damageReports->count(),
                'claims'                 => $claims->count(),
                'distribution_events'    => $distribution->count(),
                'inventory_supplies'     => InventorySupply::count(),
            ],
        ]);
    }

    /**
     * Farmers Analytics
     */
    public function farmers(Request $request)
    {
        $filters = $this->reportFilters($request);
        $farmers = FarmerProfile::query();

        if ($filters['barangay_id']) {
            $farmers->whereHas('user', function ($q) use ($filters) {
                $q->where('barangay_id', $filters['barangay_id']);
            });
        }

        return response()->json([
            'summary' => [
                'total_farmers'     => (clone $farmers)->count(),
                'rice_farmers'      => (clone $farmers)->whereHas('farms', function ($q) { $q->where('crop_type', 'Rice'); })->count(),
                'corn_farmers'      => (clone $farmers)->whereHas('farms', function ($q) { $q->where('crop_type', 'Corn'); })->count(),
                'average_farm_size' => round(Farm::avg('farm_area'), 2),
            ],

            'farmers_per_barangay' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(farmer_profiles.id) as total'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->groupBy('barangays.id', 'barangays.name')
                ->orderBy('barangays.name')
                ->get(),

            'top_barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(farmer_profiles.id) as total'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->groupBy('barangays.id', 'barangays.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),

            'sex_distribution' => FarmerProfile::select('users.sex', DB::raw('COUNT(*) as total'))
                ->join('users', 'farmer_profiles.user_id', '=', 'users.id')
                ->groupBy('users.sex')
                ->get(),

            'civil_status_distribution' => InsuranceApplication::select('civil_status', DB::raw('COUNT(*) as total'))
                ->groupBy('civil_status')
                ->get(),

            'age_groups' => [
                '18-30' => (clone $farmers)->whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 30')->count(),
                '31-45' => (clone $farmers)->whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 31 AND 45')->count(),
                '46-60' => (clone $farmers)->whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 46 AND 60')->count(),
                '61+'   => (clone $farmers)->whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 61')->count(),
            ],
        ]);
    }

    /**
     * Farm & Crop Analytics
     */
    public function farms(Request $request)
    {
        $filters = $this->reportFilters($request);
        $farms = Farm::query();

        if ($filters['crop_type']) {
            $farms->where('crop_type', $filters['crop_type']);
        }

        if ($filters['barangay_id']) {
            $farms->whereHas('farmerProfile.user', function ($q) use ($filters) {
                $q->where('barangay_id', $filters['barangay_id']);
            });
        }

        return response()->json([
            'summary' => [
                'total_farms'       => (clone $farms)->count(),
                'rice_farms'        => (clone $farms)->where('crop_type', 'Rice')->count(),
                'corn_farms'        => (clone $farms)->where('crop_type', 'Corn')->count(),
                'total_rice_area'   => round((clone $farms)->where('crop_type', 'Rice')->sum('farm_area'), 2),
                'total_corn_area'   => round((clone $farms)->where('crop_type', 'Corn')->sum('farm_area'), 2),
                'average_farm_area' => round((clone $farms)->avg('farm_area'), 2),
            ],

            'crop_distribution' => Farm::select('crop_type', DB::raw('COUNT(*) as total'))->groupBy('crop_type')->get(),
            'crop_area_distribution' => Farm::select('crop_type', DB::raw('SUM(farm_area) as total_area'))->groupBy('crop_type')->get(),

            'farms_per_barangay' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(farms.id) as total_farms'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->groupBy('barangays.id', 'barangays.name')
                ->orderBy('barangays.name')
                ->get(),

            'largest_agricultural_barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('SUM(farms.farm_area) as total_area'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->groupBy('barangays.id', 'barangays.name')
                ->orderByDesc('total_area')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Insurance Application Analytics
     */
    public function insurance(Request $request)
    {
        $filters = $this->reportFilters($request);
        $applications = InsuranceApplication::query();

        if ($filters['season_id']) {
            $applications->where('insurance_season_id', $filters['season_id']);
        }
        if ($filters['crop_type']) {
            $applications->whereHas('farm', function ($q) use ($filters) { $q->where('crop_type', $filters['crop_type']); });
        }
        if ($filters['barangay_id']) {
            $applications->whereHas('farm.farmerProfile.user', function ($q) use ($filters) { $q->where('barangay_id', $filters['barangay_id']); });
        }
        if ($filters['year']) {
            $applications->whereYear('created_at', $filters['year']);
        }

        return response()->json([
            'summary' => [
                'total_applications'       => (clone $applications)->count(),
                'submitted_to_mao'         => (clone $applications)->where('status', 'submitted_to_mao')->count(),
                'to_be_submitted_to_pcic'  => (clone $applications)->where('status', 'approved_for_pcic')->count(),
                'submitted_to_pcic'        => (clone $applications)->where('status', 'submitted_to_pcic')->count(),
                'insured'                  => (clone $applications)->where('status', 'insured')->count(),
                'rejected'                 => (clone $applications)->where('status', 'rejected')->count(),
            ],
            'status_distribution' => InsuranceApplication::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
            'crop_distribution'   => Farm::select('crop_type', DB::raw('COUNT(insurance_applications.id) as total'))
                ->join('insurance_applications', 'farms.id', '=', 'insurance_applications.farm_id')
                ->groupBy('crop_type')->get(),

            'monthly_applications' => InsuranceApplication::selectRaw("MONTH(created_at) as month, COUNT(*) as total")
                ->groupByRaw("MONTH(created_at)")->orderByRaw("MONTH(created_at)")->get(),

            'applications_per_barangay' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(insurance_applications.id) as total'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('insurance_applications', 'farms.id', '=', 'insurance_applications.farm_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total')->get(),

            'top_barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(insurance_applications.id) as total'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('insurance_applications', 'farms.id', '=', 'insurance_applications.farm_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total')->limit(10)->get(),
        ]);
    }

    /**
     * Damage Report Analytics
     */
    public function damageReports(Request $request)
    {
        $filters = $this->reportFilters($request);
        $reports = DamageReport::query();

        if ($filters['crop_type']) {
            $reports->whereHas('farm', function ($q) use ($filters) { $q->where('crop_type', $filters['crop_type']); });
        }
        if ($filters['barangay_id']) {
            $reports->whereHas('farm.farmerProfile.user', function ($q) use ($filters) { $q->where('barangay_id', $filters['barangay_id']); });
        }
        if ($filters['year']) {
            $reports->whereYear('created_at', $filters['year']);
        }

        return response()->json([
            'summary' => [
                'total_damage_reports' => (clone $reports)->count(),
                'validated_by_mao'     => (clone $reports)->where('status', 'validated_by_mao')->count(),
                'submitted_to_mao'     => (clone $reports)->where('status', 'submitted_to_mao')->count(),
            ],
            'damage_causes'  => DamageReport::select('damage_cause', DB::raw('COUNT(*) as total'))->groupBy('damage_cause')->orderByDesc('total')->get(),
            'monthly_damage' => DamageReport::selectRaw("MONTH(created_at) as month, COUNT(*) as total")->groupByRaw("MONTH(created_at)")->orderByRaw("MONTH(created_at)")->get(),
            'crop_damage'    => Farm::select('crop_type', DB::raw('COUNT(damage_reports.id) as total'))
                ->join('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')->groupBy('crop_type')->get(),

            'barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(damage_reports.id) as total_reports'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total_reports')->get(),

            'top_barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(damage_reports.id) as total_reports'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total_reports')->limit(10)->get(),
        ]);
    }

    /**
     * Claims Analytics
     */
    public function claims(Request $request)
    {
        $filters = $this->reportFilters($request);
        $claims = Claim::query();

        if ($filters['crop_type']) {
            $claims->whereHas('damageReport.farm', function ($q) use ($filters) { $q->where('crop_type', $filters['crop_type']); });
        }
        if ($filters['barangay_id']) {
            $claims->whereHas('damageReport.farm.farmerProfile.user', function ($q) use ($filters) { $q->where('barangay_id', $filters['barangay_id']); });
        }
        if ($filters['year']) {
            $claims->whereYear('created_at', $filters['year']);
        }

        return response()->json([
            'summary' => [
                'total_claims'         => (clone $claims)->count(),
                'submitted_to_pcic'    => (clone $claims)->where('status', 'submitted_to_pcic')->count(),
                'ready_for_claiming'   => (clone $claims)->where('status', 'ready_for_claiming')->count(),
                'claimed'              => (clone $claims)->where('status', 'claimed')->count(),
                'rejected'             => (clone $claims)->where('status', 'rejected')->count(),
                'total_claim_amount'   => round((clone $claims)->sum('claim_amount'), 2),
                'average_claim_amount' => round((clone $claims)->avg('claim_amount'), 2),
            ],
            'status_distribution' => Claim::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
            'monthly_claims'      => Claim::selectRaw("MONTH(created_at) as month, COUNT(*) as total, SUM(claim_amount) as amount")
                ->groupByRaw("MONTH(created_at)")->orderByRaw("MONTH(created_at)")->get(),

            'crop_claims' => Farm::select('crop_type', DB::raw('COUNT(claims.id) as total'), DB::raw('SUM(claims.claim_amount) as amount'))
                ->join('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->join('claims', 'damage_reports.id', '=', 'claims.damage_report_id')
                ->groupBy('crop_type')->get(),

            'barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('COUNT(claims.id) as total_claims'), DB::raw('SUM(claims.claim_amount) as total_amount'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->leftJoin('claims', 'damage_reports.id', '=', 'claims.damage_report_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total_amount')->get(),

            'top_barangays' => Barangay::select('barangays.id', 'barangays.name', DB::raw('SUM(claims.claim_amount) as total_amount'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->leftJoin('claims', 'damage_reports.id', '=', 'claims.damage_report_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total_amount')->limit(10)->get(),
        ]);
    }

    /**
     * Distribution Analytics
     */
    public function distribution(Request $request)
    {
        $filters = $this->reportFilters($request);

        $distribution = DistributionList::query()
            ->join(
                'distribution_events',
                'distribution_events.id',
                '=',
                'distribution_lists.distribution_event_id'
            );

        if ($filters['barangay_id']) {
            $distribution->where(
                'distribution_lists.barangay_id',
                $filters['barangay_id']
            );
        }

        if ($filters['year']) {
            $distribution->whereYear(
                'distribution_events.distribution_date',
                $filters['year']
            );
        }

        return response()->json([

            'summary' => [

                'distribution_events' => (clone $distribution)
                    ->distinct('distribution_lists.id')
                    ->count('distribution_lists.id'),

                'beneficiary_farmers' => DB::table('distribution_list_farmers')
                    ->join(
                        'distribution_lists',
                        'distribution_lists.id',
                        '=',
                        'distribution_list_farmers.distribution_list_id'
                    )
                    ->join(
                        'distribution_events',
                        'distribution_events.id',
                        '=',
                        'distribution_lists.distribution_event_id'
                    )
                    ->when($filters['barangay_id'], function ($q) use ($filters) {
                        $q->where(
                            'distribution_lists.barangay_id',
                            $filters['barangay_id']
                        );
                    })
                    ->when($filters['year'], function ($q) use ($filters) {
                        $q->whereYear(
                            'distribution_events.distribution_date',
                            $filters['year']
                        );
                    })
                    ->distinct('farmer_id')
                    ->count('farmer_id'),

                'barangays_served' => (clone $distribution)
                    ->distinct('distribution_lists.barangay_id')
                    ->count('distribution_lists.barangay_id'),

                'distributed_items' => DB::table('distribution_list_items')
                    ->join(
                        'distribution_lists',
                        'distribution_lists.id',
                        '=',
                        'distribution_list_items.distribution_list_id'
                    )
                    ->join(
                        'distribution_events',
                        'distribution_events.id',
                        '=',
                        'distribution_lists.distribution_event_id'
                    )
                    ->when($filters['barangay_id'], function ($q) use ($filters) {
                        $q->where(
                            'distribution_lists.barangay_id',
                            $filters['barangay_id']
                        );
                    })
                    ->when($filters['year'], function ($q) use ($filters) {
                        $q->whereYear(
                            'distribution_events.distribution_date',
                            $filters['year']
                        );
                    })
                    ->sum('distribution_list_items.quantity'),
            ],

            'barangays' => Barangay::select(
                    'barangays.id',
                    'barangays.name',
                    DB::raw('COUNT(distribution_lists.id) as total_events')
                )
                ->leftJoin(
                    'distribution_lists',
                    'barangays.id',
                    '=',
                    'distribution_lists.barangay_id'
                )
                ->leftJoin(
                    'distribution_events',
                    'distribution_events.id',
                    '=',
                    'distribution_lists.distribution_event_id'
                )
                ->when($filters['year'], function ($q) use ($filters) {
                    $q->whereYear(
                        'distribution_events.distribution_date',
                        $filters['year']
                    );
                })
                ->when($filters['barangay_id'], function ($q) use ($filters) {
                    $q->where(
                        'barangays.id',
                        $filters['barangay_id']
                    );
                })
                ->groupBy('barangays.id', 'barangays.name')
                ->orderByDesc('total_events')
                ->get(),

            'supplies' => InventorySupply::select(
                    'inventory_supplies.id',
                    'inventory_supplies.name as supply_name',
                    DB::raw('SUM(distribution_list_items.quantity) as total_quantity')
                )
                ->join(
                    'distribution_list_items',
                    'inventory_supplies.id',
                    '=',
                    'distribution_list_items.supply_id'
                )
                ->join(
                    'distribution_lists',
                    'distribution_lists.id',
                    '=',
                    'distribution_list_items.distribution_list_id'
                )
                ->join(
                    'distribution_events',
                    'distribution_events.id',
                    '=',
                    'distribution_lists.distribution_event_id'
                )
                ->when($filters['barangay_id'], function ($q) use ($filters) {
                    $q->where(
                        'distribution_lists.barangay_id',
                        $filters['barangay_id']
                    );
                })
                ->when($filters['year'], function ($q) use ($filters) {
                    $q->whereYear(
                        'distribution_events.distribution_date',
                        $filters['year']
                    );
                })
                ->groupBy(
                    'inventory_supplies.id',
                    'inventory_supplies.name'
                )
                ->orderByDesc('total_quantity')
                ->get(),

            'monthly_distribution' => DistributionList::join(
                    'distribution_events',
                    'distribution_events.id',
                    '=',
                    'distribution_lists.distribution_event_id'
                )
                ->when($filters['barangay_id'], function ($q) use ($filters) {
                    $q->where(
                        'distribution_lists.barangay_id',
                        $filters['barangay_id']
                    );
                })
                ->when($filters['year'], function ($q) use ($filters) {
                    $q->whereYear(
                        'distribution_events.distribution_date',
                        $filters['year']
                    );
                })
                ->selectRaw("
                    MONTH(distribution_events.distribution_date) as month,
                    COUNT(distribution_lists.id) as total_events
                ")
                ->groupByRaw("MONTH(distribution_events.distribution_date)")
                ->orderByRaw("MONTH(distribution_events.distribution_date)")
                ->get(),

            'beneficiaries' => Barangay::select(
                    'barangays.id',
                    'barangays.name',
                    DB::raw('COUNT(distribution_list_farmers.id) as total')
                )
                ->leftJoin(
                    'distribution_lists',
                    'barangays.id',
                    '=',
                    'distribution_lists.barangay_id'
                )
                ->leftJoin(
                    'distribution_events',
                    'distribution_events.id',
                    '=',
                    'distribution_lists.distribution_event_id'
                )
                ->leftJoin(
                    'distribution_list_farmers',
                    'distribution_lists.id',
                    '=',
                    'distribution_list_farmers.distribution_list_id'
                )
                ->when($filters['year'], function ($q) use ($filters) {
                    $q->whereYear(
                        'distribution_events.distribution_date',
                        $filters['year']
                    );
                })
                ->when($filters['barangay_id'], function ($q) use ($filters) {
                    $q->where(
                        'barangays.id',
                        $filters['barangay_id']
                    );
                })
                ->groupBy('barangays.id', 'barangays.name')
                ->orderByDesc('total')
                ->get(),

            'top_barangays' => Barangay::select(
                    'barangays.id',
                    'barangays.name',
                    DB::raw('COUNT(distribution_lists.id) as total')
                )
                ->leftJoin(
                    'distribution_lists',
                    'barangays.id',
                    '=',
                    'distribution_lists.barangay_id'
                )
                ->leftJoin(
                    'distribution_events',
                    'distribution_events.id',
                    '=',
                    'distribution_lists.distribution_event_id'
                )
                ->when($filters['year'], function ($q) use ($filters) {
                    $q->whereYear(
                        'distribution_events.distribution_date',
                        $filters['year']
                    );
                })
                ->when($filters['barangay_id'], function ($q) use ($filters) {
                    $q->where(
                        'barangays.id',
                        $filters['barangay_id']
                    );
                })
                ->groupBy('barangays.id', 'barangays.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Inventory Analytics
     */
    public function inventory(Request $request)
    {
        $inventory = InventorySupply::query();

        return response()->json([
            'summary' => [
                'total_supplies'    => (clone $inventory)->count(),
                
                'low_stock_items'   => (clone $inventory)->whereColumn('qty_available', '<=', 'low_threshold')->count(),
                'out_of_stock'      => (clone $inventory)->where('qty_available', 0)->count(),
            ],

            'current_inventory' => InventorySupply::select('id', 'name as supply_name', 'category', 'qty_available as quantity', 'unit', 'low_threshold as reorder_level')
                ->orderBy('name')->get(),

            'category_distribution' => InventorySupply::select('category', DB::raw('COUNT(*) as total_items'), DB::raw('SUM(qty_available) as total_quantity'))
                ->groupBy('category')->orderBy('category')->get(),

            'most_distributed' => InventorySupply::select('inventory_supplies.id', 'inventory_supplies.name as supply_name', DB::raw('SUM(distribution_list_items.quantity) as distributed'))
                ->leftJoin('distribution_list_items', 'inventory_supplies.id', '=', 'distribution_list_items.supply_id')
                ->groupBy('inventory_supplies.id', 'inventory_supplies.name')->orderByDesc('distributed')->limit(10)->get(),

            'low_stock' => InventorySupply::whereColumn('qty_available', '<=', 'low_threshold')->orderBy('qty_available')->get(),
            'out_of_stock_items' => InventorySupply::where('qty_available', 0)->get(),
        ]);
    }

    /**
     * Executive Insights
     */
    public function executive(Request $request)
    {
        $filters = $this->reportFilters($request);

        $applications = InsuranceApplication::query();
        $damageReports = DamageReport::query();
        $claims = Claim::query();
        $distribution = DistributionList::query();

        if ($filters['season_id']) {
            $applications->where('insurance_season_id', $filters['season_id']);
        }
        if ($filters['barangay_id']) {
            $applications->whereHas('farm.farmerProfile.user', function ($q) use ($filters) { $q->where('barangay_id', $filters['barangay_id']); });
            $damageReports->whereHas('farm.farmerProfile.user', function ($q) use ($filters) { $q->where('barangay_id', $filters['barangay_id']); });
            $claims->whereHas('damageReport.farm.farmerProfile.user', function ($q) use ($filters) { $q->where('barangay_id', $filters['barangay_id']); });
            $distribution->where('barangay_id', $filters['barangay_id']);
        }
        if ($filters['crop_type']) {
            $applications->whereHas('farm', function ($q) use ($filters) { $q->where('crop_type', $filters['crop_type']); });
            $damageReports->whereHas('farm', function ($q) use ($filters) { $q->where('crop_type', $filters['crop_type']); });
            $claims->whereHas('damageReport.farm', function ($q) use ($filters) { $q->where('crop_type', $filters['crop_type']); });
        }
        if ($filters['year']) {
            $applications->whereYear('created_at', $filters['year']);
            $damageReports->whereYear('created_at', $filters['year']);
            $claims->whereYear('created_at', $filters['year']);

            $distribution->whereHas('event', function ($q) use ($filters) {
                $q->whereYear('distribution_date', $filters['year']);
            });
        }

        return response()->json([
            'kpis' => [
                'registered_farmers'     => FarmerProfile::count(),
                'registered_farms'       => Farm::count(),
                'insurance_applications' => (clone $applications)->count(),
                'insured_farmers'        => (clone $applications)->where('status', 'insured')->count(),
                'damage_reports'         => (clone $damageReports)->count(),
                'claims_processed'       => (clone $claims)->whereIn('status', ['ready_for_claiming', 'claimed'])->count(),
                'claims_released_amount' => round((clone $claims)->sum('claim_amount'), 2),
                'distribution_events'    => (clone $distribution)->count(),
                'inventory_items'        => InventorySupply::count(),
                'low_stock_items'        => InventorySupply::whereColumn('qty_available', '<=', 'low_threshold')->count(),
            ],

            'top_barangays_by_farmers' => Barangay::select('barangays.name', DB::raw('COUNT(farmer_profiles.id) as total'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total')->limit(5)->get(),

            'top_damage_barangays' => Barangay::select('barangays.name', DB::raw('COUNT(damage_reports.id) as total'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('total')->limit(5)->get(),

            'top_claim_barangays' => Barangay::select('barangays.name', DB::raw('SUM(claims.claim_amount) as amount'))
                ->leftJoin('users', 'barangays.id', '=', 'users.barangay_id')
                ->leftJoin('farmer_profiles', 'users.id', '=', 'farmer_profiles.user_id')
                ->leftJoin('farms', 'farmer_profiles.id', '=', 'farms.farmer_profile_id')
                ->leftJoin('damage_reports', 'farms.id', '=', 'damage_reports.farm_id')
                ->leftJoin('claims', 'damage_reports.id', '=', 'claims.damage_report_id')
                ->groupBy('barangays.id', 'barangays.name')->orderByDesc('amount')->limit(5)->get(),

            'low_stock_supplies' => InventorySupply::whereColumn('qty_available', '<=', 'low_threshold')
                ->orderBy('qty_available')
                ->get(['id', 'name as supply_name', 'qty_available as quantity', 'low_threshold as reorder_level', 'unit']),
        ]);
    }
}