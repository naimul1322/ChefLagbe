<?php

namespace App\Services;
use App\Models\{Booking, Chef, Service, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class BookingService {
    public function pricing(float $hourly, int $hours): array {
        $subtotal = round($hourly * $hours, 2);
        $fee = round($subtotal * 0.10, 2);
        $tax = round($subtotal * 0.15, 2);
        return [
            'subtotal' => $subtotal,
            'platform_fee' => $fee,
            'tax' => $tax,
            'total_amount' => round($subtotal + $fee + $tax, 2)
        ];
    }

    public function start(string $date, string $time): CarbonImmutable {
        return CarbonImmutable::parse($date . ' ' . $time, config('app.timezone'));
    }

    public function available(Chef $chef, string $date, string $time, int $hours, ?int $exclude = null): bool {
        $start = $this->start($date, $time);
        $end = $start->addHours($hours);
        if (!$chef->is_verified || !$chef->user->is_active) {
            return false;
        }
        if ($hours < 1 || $hours > 12 || $start->lte(now())) {
            return false;
        }
        // This version supports bookings within the same day only.
        if ($end->toDateString() !== $start->toDateString()) {
            return false;
        }
        $day = strtolower($start->format('D'));
        $schedule = $chef->availability ?? [];
        $slot = $schedule[$day] ?? null;
        if (!$slot || !isset($slot['start'], $slot['end'])) {
            return false;
        }
        if ($start->format('H:i') < $slot['start'] || $end->format('H:i') > $slot['end']) {
            return false;
        }
        $query = Booking::where('chef_id', $chef->id)
            ->whereDate('booking_date', $date)
            ->where('status', 'confirmed');
        if ($exclude !== null) {
            $query->where('id', '!=', $exclude);
        }
        $existing = $query->get();
        foreach ($existing as $booking) {
            $existingStart = $this->start($booking->booking_date->format('Y-m-d'), $booking->booking_time);
            $existingEnd = $existingStart->addHours($booking->duration_hours);
            if ($start->lt($existingEnd) && $end->gt($existingStart)) {
                return false;
            }
        }
        return true;
    }

    public function create(User $customer, array $input): Booking {
        return DB::transaction(function () use ($customer, $input) {
            $chef = Chef::listed()->lockForUpdate()->findOrFail($input['chef_id']);
            Service::where('is_active', true)->findOrFail($input['service_id']);
            if (!$this->available($chef, $input['booking_date'], $input['booking_time'], $input['duration_hours'])) {
                throw ValidationException::withMessages(['booking_time' => 'Chef is unavailable for this date and time. Choose another slot.']);
            }
            $currentBooking = Booking::create(array_merge($input, $this->pricing((float)$chef->price_per_hour, (int)$input['duration_hours']), [
                'customer_id' => $customer->id,
                'booking_ref' => 'CL-' . Str::upper(Str::random(12)),
                'status' => 'pending'
            ]));
            return $currentBooking;
        });
    }

    public function expirePending(Booking $booking): bool {
        return DB::transaction(function () use ($booking) {
            Chef::lockForUpdate()->findOrFail($booking->chef_id);
            $currentBooking = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($currentBooking->status !== 'pending' || $this->start($currentBooking->booking_date->format('Y-m-d'), $currentBooking->booking_time)->gt(now())) {
                return false;
            }
            $currentBooking->update(['status' => 'cancelled']);
            $currentBooking->payments()->where('status', 'success')->update(['status' => 'refunded']);
            $email = $currentBooking->customer->email;
            $ref = $currentBooking->booking_ref;
            DB::afterCommit(fn() => Notify::send($email, 'Booking request expired', $ref . ' expired before confirmation. Any simulated payment was refunded.'));
            return true;
        });
    }

    public function change(Booking $booking, string $status, string $role): Booking {
        return DB::transaction(function () use ($booking, $status, $role) {
            $chef = Chef::lockForUpdate()->findOrFail($booking->chef_id);
            $currentBooking = Booking::lockForUpdate()->findOrFail($booking->id);
            $current = $currentBooking->status;
            if ($current === $status) {
                return $currentBooking;
            }
            $allowed = match ($role) {
                'customer' => ['pending' => ['cancelled'], 'confirmed' => ['cancelled']],
                'chef', 'admin' => ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled']],
                default => []
            };
            abort_unless(in_array($status, $allowed[$current] ?? [], true), 422, 'This status change is not allowed.');
            $start = $this->start($currentBooking->booking_date->format('Y-m-d'), $currentBooking->booking_time);
            if ($role === 'customer' && $status === 'cancelled') {
                abort_if($start->lt(now()->addHours(48)), 422, 'Cancellation requires at least 48 hours before the booking starts.');
            }
            if ($status === 'confirmed') {
                abort_unless($chef->is_verified && $chef->user->is_active, 422, 'Chef is not active and verified.');
                abort_unless($currentBooking->payments()->where('status', 'success')->exists(), 422, 'A successful simulated payment is required first.');
                abort_unless($this->available($chef, $currentBooking->booking_date->format('Y-m-d'), substr($currentBooking->booking_time, 0, 5), $currentBooking->duration_hours, $currentBooking->id), 422, 'Chef is not available or the slot overlaps another confirmed booking.');
            }
            if ($status === 'completed') {
                abort_if($start->addHours($currentBooking->duration_hours)->gt(now()), 422, 'Bookings cannot be completed before their scheduled end.');
            }
            $currentBooking->update(['status' => $status]);
            if ($status === 'confirmed') {
                // One confirmed reservation wins. Close overlapping pending requests and refund simulated payments.
                $end = $start->addHours($currentBooking->duration_hours);
                foreach (Booking::where('chef_id', $chef->id)->whereDate('booking_date', $currentBooking->booking_date->format('Y-m-d'))->where('status', 'pending')->where('id', '!=', $currentBooking->id)->lockForUpdate()->get() as $other) {
                    $otherStart = $this->start($other->booking_date->format('Y-m-d'), $other->booking_time);
                    if ($start->lt($otherStart->addHours($other->duration_hours)) && $end->gt($otherStart)) {
                        $other->update(['status' => 'cancelled']);
                        $other->payments()->where('status', 'success')->update(['status' => 'refunded']);
                        $email = $other->customer->email;
                        $ref = $other->booking_ref;
                        DB::afterCommit(fn() => Notify::send($email, 'Booking slot taken', $ref . ' was cancelled because an overlapping request was confirmed. Any simulated payment was refunded.'));
                    }
                }
            }
            if ($status === 'cancelled') {
                $currentBooking->payments()->where('status', 'success')->update(['status' => 'refunded']);
            }
            return $currentBooking->fresh();
        });
    }
}
