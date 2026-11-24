<?php

use Illuminate\Support\Facades\{Artisan, Schedule, Cache};
use App\Models\Booking;
use App\Services\Notify;
Artisan::command('cheflagbe:reminders', function () {
    Booking::with('customer')->where('status', 'confirmed')->whereDate('booking_date', now()->addDay()->toDateString())->each(function ($b) {
        if (Cache::add('reminder-' . $b->id, true, 172800)) Notify::send($b->customer->email, 'Booking reminder', 'Your ChefLagbe booking ' . $b->booking_ref . ' is scheduled for tomorrow.');
    });
    $this->info('Reminder check complete.');
});
Schedule::command('cheflagbe:reminders')->dailyAt('09:00');
Artisan::command('cheflagbe:expire-pending', function () {
    $count = 0;
    $service = app(\App\Services\BookingService::class);
    Booking::where('status', 'pending')->whereDate('booking_date', '<=', now()->toDateString())->chunkById(100, function ($bookings) use ($service, &$count) {
        foreach ($bookings as $booking) if ($service->expirePending($booking)) $count++;
    });
    $this->info("Expired {$count} pending request(s).");
});
Schedule::command('cheflagbe:expire-pending')->hourly()->withoutOverlapping();
