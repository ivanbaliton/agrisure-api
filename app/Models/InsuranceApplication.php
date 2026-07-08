<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'insurance_season_id',
        // Personal Information
        'civil_status',
        'beneficiary_name',
        'spouse_name',
        'parent_guardian_name',

        // Crop Information
        'variety',
        'farm_type',
        'sowing_date',
        'transplanting_date',

        // NEWS Information
        'north_boundary',
        'east_boundary',
        'west_boundary',
        'south_boundary',

        // Land Information
        'is_land_owner',
        'tenure_status',

        // Insurance Information
        'application_date',
        'status',
        'signature_path',

        'covered_free_area',
        'excess_area',
        'premium_amount',
        'payment_status',
        'payment_method',
        'payment_proof_path',
        'gcash_reference_number',
        'payment_submitted_at',

        'insured_area',
        'free_coverage_before',
        'free_coverage_after',

        // Offline Sync Support
        'client_uuid',
        'sync_source',
        'captured_at',
    ];

    protected $casts = [
        'application_date' => 'date',
        'sowing_date' => 'date',
        'transplanting_date' => 'date',
        'is_land_owner' => 'boolean',
        'covered_free_area' => 'decimal:2',
        'excess_area' => 'decimal:2',
        'premium_amount' => 'decimal:2',
        'insured_area' => 'decimal:2',
        'free_coverage_before' => 'decimal:2',
        'free_coverage_after' => 'decimal:2',
        'captured_at' => 'datetime',
        'payment_submitted_at' => 'datetime',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function farmerProfile()
    {
        return $this->hasOneThrough(
            FarmerProfile::class,
            Farm::class,
            'id',
            'id',
            'farm_id',
            'farmer_profile_id'
        );
    }

        public function season()
    {
        return $this->belongsTo(
            InsuranceSeason::class,
            'insurance_season_id'
        );
    }

    public function damageReports()
{
    return $this->hasMany(
        DamageReport::class,
        'insurance_application_id'
    );
}
}