<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionEvent extends Model
{
    protected $fillable = [
        'reference_no',
        'title',
        'distribution_date',
        'distribution_time',
        'venue',
        'description',
        'status',
        'published_at',
        'completed_at',
    ];

    public function lists()
    {
        return $this->hasMany(
            DistributionList::class,
            'distribution_event_id'
        );
    }
}