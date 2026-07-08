<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventorySupply extends Model
{
    protected $table = 'inventory_supplies';

    protected $fillable = [
        'name',
        'category',
        'unit',
        'qty_available',
        'qty_distributed',
        'low_threshold',
        'status'
    ];

    public function distributionItems()
    {
        return $this->hasMany(DistributionItem::class, 'supply_id');
    }

    public function distributionAllocations()
    {
        return $this->hasMany(DistributionAllocation::class, 'supply_id');
    }
}