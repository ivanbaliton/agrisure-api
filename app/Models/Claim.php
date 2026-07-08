<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $fillable = [
        'damage_report_id',
        'inspection_date',
        'submitted_to_pcic_at',
        'pcic_status',
        'claim_amount',
        'pcic_remarks',
        'claim_schedule',
        'claim_venue',
        'claimed_at',
        'status',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'submitted_to_pcic_at' => 'datetime',
        'claim_schedule' => 'date',
        'claimed_at' => 'datetime',
        'claim_amount' => 'decimal:2',
    ];

    /**
     * The damage report associated with this claim.
     */
    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    /**
     * Get the insurance season directly through the damage report.
     */
    public function season()
    {
        return $this->hasOneThrough(
            InsuranceSeason::class,
            DamageReport::class,
            'id',                  // Foreign key on damage_reports table (local to claim link)
            'id',                  // Foreign key on insurance_seasons table
            'damage_report_id',    // Local key on claims table
            'insurance_season_id'  // Local key on damage_reports table
        );
    }

    /**
     * Convenience relation to the farm.
     */
    public function farm()
    {
        return $this->hasOneThrough(
            Farm::class,
            DamageReport::class,
            'id',                // Foreign key on damage_reports
            'id',                // Foreign key on farms
            'damage_report_id',  // Local key on claims
            'farm_id'            // Local key on damage_reports
        );
    }

    /**
     * Convenience relation to the farmer profile.
     */
    public function farmerProfile()
    {
        return $this->hasOneThrough(
            FarmerProfile::class,
            Farm::class,
            'id',                  // Foreign key on farms
            'id',                  // Foreign key on farmer_profiles
            'farm_id',             // Local key on damage_reports->farm
            'farmer_profile_id'
        );
    }
}