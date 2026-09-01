<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionEvent extends Model
{
    protected $fillable = [
        'reference_no',
        'letter_image',          // ✅ Add this
        'title',
        'distribution_date',
        'distribution_time',
        'venue',
        'description',
        'status',
        'published_at',
        'completed_at',
    ];

    protected $casts = [
        'distribution_date' => 'date',
        'distribution_time' => 'datetime:H:i',
        'published_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function lists()
    {
        return $this->hasMany(
            DistributionList::class,
            'distribution_event_id'
        );
    }
}