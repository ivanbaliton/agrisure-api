<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionItem extends Model
{
    protected $table = 'distribution_list_items';

    protected $fillable = [
        'distribution_list_id',
        'supply_id',
        'quantity',
       
    ];

    public function distributionList()
    {
        return $this->belongsTo(DistributionList::class);
    }

    public function supply()
    {
        return $this->belongsTo(InventorySupply::class, 'supply_id');
    }
}