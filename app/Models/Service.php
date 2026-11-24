<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Service extends Model {
    protected $fillable = ['name', 'description', 'icon', 'base_price', 'duration_hours', 'is_active'];

    protected $casts = ['base_price' => 'decimal:2', 'is_active' => 'boolean'];

    public function bookings() {
        return $this->hasMany(Booking::class);
    }
}
