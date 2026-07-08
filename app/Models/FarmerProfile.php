<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FarmerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_or_phone',
        'birthdate',
        'address',
        'rsbsa_reference',
         'profile_photo',
    ];

    // Link back to User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function farms()
    {
        return $this->hasMany(Farm::class);
    }
    
}