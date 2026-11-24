<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{User, Chef, Service, Booking, Review, Payment};
use App\Services\BookingService;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo seeding is disabled in production.');
        }
        $demo = json_decode(file_get_contents(__DIR__ . '/demo-data.json'), true);
        $password = Hash::make('DemoPass123!');
        User::firstOrCreate(['email' => 'admin@cheflagbe.test'], [
            'first_name' => 'Demo',
            'last_name' => 'Admin',
            'password' => $password,
            'user_type' => 'admin',
            'is_active' => true,
            'location' => 'Dhaka',
            'email_verified_at' => now()
        ]);
        $customers = [];
        for ($i = 1; $i <= 5; $i++) $customers[] = User::firstOrCreate(['email' => "customer$i@cheflagbe.test"], [
            'first_name' => 'Demo',
            'last_name' => 'Customer ' . $i,
            'password' => $password,
            'user_type' => 'customer',
            'is_active' => true,
            'phone' => '01000000000',
            'location' => 'Dhaka',
            'email_verified_at' => now()
        ]);
        $chefs = [];
        foreach ($demo['chefs'] as $chefData) {
            $user = User::firstOrCreate(['email' => $chefData['email']], [
                'first_name' => $chefData['first_name'],
                'last_name' => $chefData['last_name'],
                'location' => $chefData['location'],
                'phone' => '01000000000',
                'user_type' => 'chef',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now()
            ]);
            $chefs[] = Chef::firstOrCreate(['user_id' => $user->id], [
                'specialty' => $chefData['specialty'],
                'bio' => $chefData['bio'],
                'price_per_hour' => $chefData['price_per_hour'],
                'experience_years' => $chefData['experience_years'],
                'availability' => $chefData['availability'],
                'is_verified' => true
            ]);
        }
        $services = [];
        foreach ($demo['services'] as $serviceData) {
            unset($serviceData['id']);
            $services[] = Service::firstOrCreate(['name' => $serviceData['name']], $serviceData);
        }
        $bookingService = app(BookingService::class);
        for ($i = 1; $i <= 20; $i++) {
            $chef = $chefs[($i - 1) % 10];
            $customer = $customers[($i - 1) % 5];
            $service = $services[($i - 1) % 6];
            $status = $i <= 15 ? 'completed': ($i <= 17 ? 'confirmed': ($i <= 19 ? 'pending': 'cancelled'));
            $booking = Booking::firstOrCreate(['booking_ref' => 'DEMO-BOOK-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT)], array_merge([
                'customer_id' => $customer->id,
                'chef_id' => $chef->id,
                'service_id' => $service->id,
                'booking_date' => ($i <= 15 ? now()->subDays(50 - $i): now()->addDays($i))->toDateString(),
                'booking_time' => '11:00',
                'duration_hours' => 3,
                'guests' => 4,
                'status' => $status,
                'special_instructions' => 'Fictional demo booking only.'
            ], $bookingService->pricing((float)$chef->price_per_hour, 3)));
            if ($i <= 17 || $i === 20) Payment::firstOrCreate(['transaction_id' => 'DEMO-TXN-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT)], [
                'booking_id' => $booking->id,
                'method' => ['card', 'bkash', 'nagad', 'rocket', 'cash'][$i % 5],
                'amount' => $booking->total_amount,
                'status' => $i === 20 ? 'refunded': 'success',
                'paid_at' => now()->subDays($i % 28)
            ]);
            if ($i <= 15) Review::firstOrCreate(['booking_id' => $booking->id], [
                'customer_id' => $customer->id,
                'chef_id' => $chef->id,
                'rating' => $i % 3 === 0 ? 4: 5,
                'comment' => 'Illustrative review for development data only.'
            ]);
        }
        foreach ($chefs as $chef) {
            $chef->recalculateRating();
        }
    }
}
