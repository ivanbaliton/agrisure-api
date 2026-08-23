<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    use HasFactory;

    protected $fillable = [
        'damage_report_id',
        'crop_stage_at_loss',
        'area_damaged',
        'degree_of_damage',
        'expected_harvest_date',
        'cost_land_preparation',
        'cost_seedling_transplanting',
        'cost_seeds',
        'cost_fertilizer',
        'cost_chemicals',
        'cost_others',
        'total_production_cost',
        'claim_filed_date',
        'inspection_date',
        'submitted_to_pcic_at',
        'pcic_status',
        'pcic_remarks',
        'claim_schedule',
        'claim_venue',
        'claimed_at',
        'status',
    ];

    protected $casts = [
        'area_damaged'                => 'decimal:2',
        'degree_of_damage'            => 'decimal:2',
        'cost_land_preparation'       => 'decimal:2',
        'cost_seedling_transplanting' => 'decimal:2',
        'cost_seeds'                  => 'decimal:2',
        'cost_fertilizer'             => 'decimal:2',
        'cost_chemicals'              => 'decimal:2',
        'cost_others'                 => 'decimal:2',
        'total_production_cost'       => 'decimal:2',
        'claim_filed_date'            => 'date',
        'inspection_date'             => 'date',
        'submitted_to_pcic_at'        => 'datetime',
        'claim_schedule'              => 'date',
        'claimed_at'                  => 'datetime',
    ];

    /**
     * The damage report associated with this claim.
     */
    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }
}