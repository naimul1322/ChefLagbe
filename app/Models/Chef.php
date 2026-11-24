<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Chef extends Model {
    protected $fillable = [
        'user_id',
        'specialty',
        'bio',
        'price_per_hour',
        'experience_years',
        'rating',
        'total_reviews',
        'is_verified',
        'availability',
        'profile_image',
        'verification_docs'
    ];

    protected $hidden = ['verification_docs'];

    protected $casts = [
        'price_per_hour' => 'decimal:2',
        'rating' => 'decimal:2',
        'availability' => 'array',
        'is_verified' => 'boolean'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function bookings() {
        return $this->hasMany(Booking::class);
    }

    public function reviews() {
        return $this->hasMany(Review::class);
    }

    public function scopeListed($q) {
        return $q->where('is_verified', true)->whereHas('user', fn($u) => $u->where('is_active', true));
    }

    public function publicData(): array {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'location' => $this->user->location,
            'specialty' => $this->specialty,
            'bio' => $this->bio,
            'price_per_hour' => (float)$this->price_per_hour,
            'experience_years' => $this->experience_years,
            'rating' => (float)$this->rating,
            'total_reviews' => $this->total_reviews,
            'is_verified' => $this->is_verified,
            'availability' => $this->availability,
            'profile_image' => $this->profile_image
        ];
    }

    public function recalculateRating(): void {
        $this->update([
            'rating' => round((float)$this->reviews()->avg('rating'), 2),
            'total_reviews' => $this->reviews()->count()
        ]);
    }
}
