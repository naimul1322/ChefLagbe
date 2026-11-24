<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Booking extends Model {
    protected $fillable = [
        'booking_ref',
        'customer_id',
        'chef_id',
        'service_id',
        'booking_date',
        'booking_time',
        'duration_hours',
        'guests',
        'cuisine',
        'dietary_notes',
        'special_instructions',
        'status',
        'subtotal',
        'platform_fee',
        'tax',
        'total_amount'
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'subtotal' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    public function customer() {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function chef() {
        return $this->belongsTo(Chef::class);
    }

    public function service() {
        return $this->belongsTo(Service::class);
    }

    public function payments() {
        return $this->hasMany(Payment::class);
    }

    public function review() {
        return $this->hasOne(Review::class);
    }

    public function data(): array {
        $this->loadMissing(['chef.user', 'customer', 'service', 'payments', 'review']);
        return [
            'id' => $this->id,
            'booking_ref' => $this->booking_ref,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer->name,
            'chef_id' => $this->chef_id,
            'chef_name' => $this->chef->user->name,
            'service_id' => $this->service_id,
            'service' => $this->service->name,
            'booking_date' => $this->booking_date->format('Y-m-d'),
            'booking_time' => substr($this->booking_time, 0, 5),
            'duration_hours' => $this->duration_hours,
            'guests' => $this->guests,
            'cuisine' => $this->cuisine,
            'dietary_notes' => $this->dietary_notes,
            'special_instructions' => $this->special_instructions,
            'status' => $this->status,
            'subtotal' => (float)$this->subtotal,
            'platform_fee' => (float)$this->platform_fee,
            'tax' => (float)$this->tax,
            'total_amount' => (float)$this->total_amount,
            'paid' => $this->payments->contains('status', 'success'),
            'reviewed' => (bool)$this->review,
            'payment' => $this->payments->sortByDesc('id')->first()?->only(['id', 'method', 'amount', 'status', 'transaction_id', 'paid_at'])
        ];
    }
}
