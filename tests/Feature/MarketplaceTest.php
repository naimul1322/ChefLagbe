<?php

namespace Tests\Feature;
use App\Models\{User, Chef, Booking, Payment};
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class MarketplaceTest extends TestCase {
    use RefreshDatabase;

    protected $seed = true;

    public function test_public_listing_is_paginated_and_filterable(): void {
        $this->getJson('/api/chefs?location=Dhaka')->assertOk()->assertJsonStructure(['data', 'total', 'current_page']);
        $this->getJson('/api/chefs?max_price=100')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_customer_signup_and_login(): void {
        $d = [
            'first_name' => 'Demo',
            'last_name' => 'New',
            'email' => 'new@example.test',
            'phone' => '01000000000',
            'location' => 'Dhaka',
            'password' => 'Example123!',
            'password_confirmation' => 'Example123!',
            'user_type' => 'customer',
            'terms' => true
        ];
        $this->postJson('/api/auth/signup', $d)->assertCreated()->assertJsonPath('user.user_type', 'customer');
        $this->postJson('/api/auth/login', [
            'email' => $d['email'],
            'password' => $d['password'],
            'device_name' => 'phpunit'
        ])->assertOk()->assertJsonStructure(['token', 'user']);
        $d['email'] = 'bad@example.test';
        $d['user_type'] = 'admin';
        $this->postJson('/api/auth/signup', $d)->assertUnprocessable();
    }

    public function test_token_logout(): void {
        $u = User::where('user_type', 'customer')->firstOrFail();
        $token = $u->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'test']);
    }

    public function test_admin_access_is_restricted(): void {
        Sanctum::actingAs(User::where('user_type', 'customer')->firstOrFail());
        $this->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_admin_stats(): void {
        Sanctum::actingAs(User::where('user_type', 'admin')->firstOrFail());
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonStructure(['users', 'chefs', 'bookings', 'revenue', 'monthly']);
    }

    public function test_pricing_and_payment_use_server_totals(): void {
        $customer = User::where('user_type', 'customer')->firstOrFail();
        Sanctum::actingAs($customer);
        $chef = Chef::firstOrFail();
        $date = now()->addDays(40)->toDateString();
        $d = [
            'chef_id' => $chef->id,
            'service_id' => 1,
            'booking_date' => $date,
            'booking_time' => '10:00',
            'duration_hours' => 3,
            'guests' => 4,
            'special_instructions' => 'Demo venue',
            'total_amount' => 1
        ];
        $this->getJson('/api/chefs/' . $chef->id . '/availability?' . http_build_query([
            'booking_date' => $date,
            'booking_time' => '10:00',
            'duration_hours' => 3
        ]))->assertOk()->assertJsonPath('available', true);
        $b = $this->postJson('/api/bookings', $d)->assertCreated()->json();
        $expected = app(BookingService::class)->pricing((float)$chef->price_per_hour, 3);
        $this->assertEquals($expected['total_amount'], $b['total_amount']);
        $this->postJson('/api/payments', [
            'booking_id' => $b['id'],
            'method' => 'bkash',
            'outcome' => 'failed'
        ])->assertCreated()->assertJsonPath('payment.status', 'failed');
        $p = $this->postJson('/api/payments', [
            'booking_id' => $b['id'],
            'method' => 'bkash',
            'outcome' => 'success',
            'amount' => 1
        ])->assertCreated()->json('payment');
        $this->assertEquals($expected['total_amount'], (float)$p['amount']);
        $this->postJson('/api/payments', [
            'booking_id' => $b['id'],
            'method' => 'card',
            'outcome' => 'success'
        ])->assertCreated()->assertJsonPath('payment.id', $p['id']);
        $this->deleteJson('/api/bookings/' . $b['id'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('payments', ['id' => $p['id'], 'status' => 'refunded']);
    }

    public function test_duplicate_reviews_and_booking_ownership(): void {
        $b = Booking::where('status', 'completed')->firstOrFail();
        $u = User::where('user_type', 'customer')->where('id', '!=', $b->customer_id)->firstOrFail();
        Sanctum::actingAs($u);
        $this->getJson('/api/bookings/' . $b->id)->assertNotFound();
        Sanctum::actingAs($b->customer);
        $this->postJson('/api/reviews', [
            'booking_id' => $b->id,
            'rating' => 5,
            'comment' => 'duplicate'
        ])->assertUnprocessable();
    }

    public function test_confirmed_overlap_is_rejected(): void {
        $c = Chef::firstOrFail();
        $u = User::where('user_type', 'customer')->firstOrFail();
        $svc = app(BookingService::class);
        $date = now()->addDays(50)->toDateString();
        $d = [
            'chef_id' => $c->id,
            'service_id' => 1,
            'booking_date' => $date,
            'booking_time' => '11:00',
            'duration_hours' => 3,
            'guests' => 4,
            'special_instructions' => 'Sample'
        ];
        Sanctum::actingAs($u);
        $a = $this->postJson('/api/bookings', $d)->assertCreated()->json();
        $b = $this->postJson('/api/bookings', $d)->assertCreated()->json();
        foreach ([$a, $b] as $row) $this->postJson('/api/payments', [
            'booking_id' => $row['id'],
            'method' => 'cash',
            'outcome' => 'success'
        ])->assertCreated();
        Sanctum::actingAs($c->user);
        $this->putJson('/api/bookings/' . $a['id'], ['status' => 'confirmed'])->assertOk();
        $this->putJson('/api/bookings/' . $b['id'], ['status' => 'confirmed'])->assertUnprocessable();
        $this->assertDatabaseHas('bookings', ['id' => $b['id'], 'status' => 'cancelled']);
        $this->assertDatabaseHas('payments', ['booking_id' => $b['id'], 'status' => 'refunded']);
    }

    public function test_contact_submission(): void {
        $this->postJson('/api/contact', [
            'first_name' => 'Demo',
            'last_name' => 'Person',
            'email' => 'contact@example.test',
            'subject' => 'General inquiry',
            'message' => 'This is a fictional test.'
        ])->assertCreated();
        $this->assertDatabaseHas('contacts', ['email' => 'contact@example.test']);
    }
}
