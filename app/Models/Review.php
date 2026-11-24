<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Review extends Model {
    protected $fillable = ['booking_id', 'customer_id', 'chef_id', 'rating', 'comment'];

    public function booking() {
        return $this->belongsTo(Booking::class);
    }

    public function customer() {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function chef() {
        return $this->belongsTo(Chef::class);
    }

    public function data() {
        return [
            'id' => $this->id,
            'chef_id' => $this->chef_id,
            'customer_name' => $this->customer->name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'date' => $this->created_at->toDateString()
        ];
    }
}
