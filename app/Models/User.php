<?php

namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable implements MustVerifyEmail {
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'location',
        'password',
        'user_type',
        'is_active',
        'avatar',
        'email_verified_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function getNameAttribute() {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function chef() {
        return $this->hasOne(Chef::class);
    }

    public function bookings() {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function reviews() {
        return $this->hasMany(Review::class, 'customer_id');
    }
    // Fields that can safely be returned to the signed-in user.
    public function publicData() {
        return $this->only([
            'id', 'first_name', 'last_name', 'email', 'phone', 'location',
            'user_type', 'is_active', 'avatar', 'email_verified_at'
        ]);
    }
}
