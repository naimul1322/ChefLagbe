<?php

namespace Tests\Feature;
use App\Models\{User, Chef, Booking, Payment};
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class RegressionTest extends TestCase {
    use RefreshDatabase;

    protected $seed = true;

    public function test_expired_pending_booking_cannot_be_paid_and_expires(): void {
        $b = Booking::where('status', 'pending')->firstOrFail();
        $b->update(['booking_date' => now()->subDay()->toDateString()]);
        Sanctum::actingAs($b->customer);
        $this->postJson('/api/payments', [
            'booking_id' => $b->id,
            'method' => 'cash',
            'outcome' => 'success'
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('payments', ['booking_id' => $b->id, 'status' => 'success']);
        $this->assertTrue(app(BookingService::class)->expirePending($b));
        $this->assertDatabaseHas('bookings', ['id' => $b->id, 'status' => 'cancelled']);
    }

    public function test_paused_availability_blocks_new_requests(): void {
        $chef = Chef::firstOrFail();
        Sanctum::actingAs($chef->user);
        $this->putJson('/api/chef/availability', ['availability' => []])->assertOk();
        $date = now()->addDays(60)->toDateString();
        $this->getJson('/api/chefs/' . $chef->id . '/availability?' . http_build_query([
            'booking_date' => $date,
            'booking_time' => '10:00',
            'duration_hours' => 2
        ]))->assertOk()->assertJsonPath('available', false);
    }

    public function test_full_name_search_and_disabled_chef(): void {
        $chef = Chef::firstOrFail();
        $this->getJson('/api/chefs?' . http_build_query(['q' => $chef->user->name]))->assertOk()->assertJsonFragment(['id' => $chef->id]);
        $chef->user->update(['is_active' => false]);
        $this->getJson('/api/chefs/' . $chef->id)->assertNotFound();
    }

    public function test_response_security_headers(): void {
        $this->getJson('/api/services')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_overlong_password_is_rejected_not_server_error(): void {
        $this->postJson('/api/auth/login', ['email' => 'customer1@cheflagbe.test', 'password' => str_repeat('a', 73)])->assertUnprocessable();
    }
}
