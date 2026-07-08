<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsuranceSeason extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_name',
        'deadline_date',
        'status',
        'is_default',
    ];

    protected $casts = [
        'deadline_date' => 'date',
        'is_default' => 'boolean',
    ];

    public function applications()
    {
        return $this->hasMany(
            InsuranceApplication::class,
            'insurance_season_id'
        );
    }
}