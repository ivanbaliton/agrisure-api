<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReport extends Model
{
    protected $fillable = [
        'farm_id',
        'insurance_application_id',
        'damage_cause',
        'damage_date',
        'damage_image_path',
        'report_latitude',
        'report_longitude',
        'distance_from_farm',
        'is_suspicious',
        'status',
        'client_uuid',
        'sync_source',
        'captured_at',
    ];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'damage_date' => 'date',
        'captured_at' => 'datetime',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function claim()
    {
        return $this->hasOne(Claim::class, 'damage_report_id');
    }

    public function insuranceApplication()
    {
        return $this->belongsTo(InsuranceApplication::class, 'insurance_application_id');
    }
}