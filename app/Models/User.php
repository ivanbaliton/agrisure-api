<?php

namespace App\Models;

use App\Models\Barangay;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const ROLE_FARMER = 'farmer';
    const ROLE_MAO = 'mao';
    const ROLE_BARANGAY = 'barangay';

    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'extension_name',
        'sex',
        'email',
        'phone_number',
        'password',
        'role',
        'barangay_id',
        'account_status',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function farmerProfile()
    {
        return $this->hasOne(FarmerProfile::class, 'user_id', 'id');
    }



    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(\App\Models\Notification::class);
    }

    public function isFarmer()
    {
        return $this->role === self::ROLE_FARMER;
    }

    public function isMao()
    {
        return $this->role === self::ROLE_MAO;
    }

    public function isBarangay()
    {
        return $this->role === self::ROLE_BARANGAY;
    }

    public function isVerified()
    {
        return $this->account_status === self::STATUS_VERIFIED;
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }
}