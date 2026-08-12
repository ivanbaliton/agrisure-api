<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'distribution_event_id',
        'farmer_id',
        'channel',
        'status',
        'error_message',
    ];

    /**
     * Get the distribution event associated with this log.
     */
    public function distributionEvent(): BelongsTo
    {
        return $this->belongsTo(DistributionEvent::class);
    }

    /**
     * Get the farmer (User) who received this notification.
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }
}