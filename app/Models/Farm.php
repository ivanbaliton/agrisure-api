<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farm extends Model
{
    protected $fillable = [
        'farmer_profile_id',
        'farm_name',
        'crop_type',
        'farm_area',
        'farm_image_path',
        'latitude',
        'longitude',
        'insurance_status',

        // Offline support
        'client_uuid',
        'sync_source',
        'captured_at',
    ];

    protected $casts = [
        'farm_area' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'captured_at' => 'datetime',
    ];

    public function farmerProfile()
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function insuranceApplications()
    {
        return $this->hasMany(
            InsuranceApplication::class
        );
    }

    public function damageReports()
    {
        return $this->hasMany(
            DamageReport::class
        );
    }

    public function activeApplication()
{
    return $this->hasOne(InsuranceApplication::class)
        ->whereHas('season', function ($query) {
            $query->where('status', 'application_open');
        })
        ->whereIn('status', [
            'submitted_to_mao', 
            'approved_for_pcic', 
            'submitted_to_pcic', 
            'insured'
        ])
        ->latest();
}

// Appends `insurance_status` dynamically to JSON responses
protected $appends = ['insurance_status'];

public function getInsuranceStatusAttribute()
{
    return $this->activeApplication ? $this->activeApplication->status : 'not_insured';
}
}