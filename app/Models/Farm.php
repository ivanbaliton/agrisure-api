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
}