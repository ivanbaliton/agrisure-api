<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionAllocation extends Model
{
    protected $table = 'distribution_list_allocations';

    protected $fillable = [
        'distribution_list_id',
        'farmer_id',
        'supply_id',
        'quantity',
    ];

    public function distributionList()
    {
        return $this->belongsTo(DistributionList::class);
    }

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function supply()
    {
        return $this->belongsTo(InventorySupply::class, 'supply_id');
    }
}