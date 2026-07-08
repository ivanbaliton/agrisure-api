<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionList extends Model
{
    protected $fillable = [
        'distribution_event_id',
        'barangay_id',
        'status',
        'published_at',
        'completed_at',
    ];

    public function event()
    {
        return $this->belongsTo(
            DistributionEvent::class,
            'distribution_event_id'
        );
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function items()
    {
        return $this->hasMany(DistributionItem::class);
    }

    public function farmers()
    {
        return $this->hasMany(DistributionFarmer::class);
    }

    public function allocations()
    {
        return $this->hasMany(DistributionAllocation::class);
    }
}