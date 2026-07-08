<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionFarmer extends Model
{
    protected $table = 'distribution_list_farmers';

    protected $fillable = [
        'distribution_list_id',
        'farmer_id',
        'claim_status',
        'received_at',
    ];

    protected $casts = [
        'claim_status' => 'string',
        'received_at' => 'datetime',
    ];

    public function distributionList()
    {
        return $this->belongsTo(DistributionList::class);
    }

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }
}