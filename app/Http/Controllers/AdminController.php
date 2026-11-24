<?php

namespace App\Http\Controllers;
use App\Models\{User, Chef, Booking, Payment, Review, Contact};
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class AdminController {
    public function stats() {
        return [
            'users' => User::where('user_type', '!=', 'admin')->count(),
            'chefs' => Chef::count(),
            'bookings' => Booking::count(),
            'revenue' => (float)Payment::where('status', 'success')->sum('amount'),
            'pending_chefs' => Chef::where('is_verified', false)->count(),
            'recent' => Booking::with(['chef.user', 'customer', 'service', 'payments', 'review'])->latest()->limit(6)->get()->map(fn(Booking $booking) => $booking->data()),
            'activity' => $this->activity()['data'],
            'monthly' => $this->revenue()['data']
        ];
    }

    public function users() {
        return [
            'data' => User::select('id', 'first_name', 'last_name', 'email', 'user_type', 'is_active', 'location', 'created_at')->latest()->limit(200)->get()
        ];
    }

    public function userStatus(Request $request, $id) {
        $request->validate(['is_active' => 'required|boolean']);
        $user = User::findOrFail($id);
        abort_if($user->user_type === 'admin', 403, 'Administrator accounts cannot be modified here.');
        $user->update(['is_active' => $request->boolean('is_active')]);
        if (!$user->is_active) {
            $user->tokens()->delete();
        }
        return ['message' => 'Account status updated.'];
    }

    public function chefs() {
        return [
            'data' => Chef::with('user')->latest()->limit(200)->get()->map(fn(Chef $chef) => array_merge($chef->publicData(), ['has_documents' => (bool)$chef->verification_docs, 'is_active' => $chef->user->is_active]))
        ];
    }

    public function verify(Request $request, $id) {
        $request->validate(['is_verified' => 'required|boolean']);
        $chef = Chef::findOrFail($id);
        if ($request->boolean('is_verified')) {
            abort_unless($chef->verification_docs, 422, 'Verification documents are required.');
            abort_unless($chef->user->is_active, 422, 'Chef account is inactive.');
        }
        $chef->update(['is_verified' => $request->boolean('is_verified')]);
        return ['message' => 'Verification status updated.'];
    }

    public function documents($id) {
        $chef = Chef::findOrFail($id);
        abort_unless($chef->verification_docs && Storage::disk('local')->exists($chef->verification_docs), 404);
        return Storage::disk('local')->download($chef->verification_docs, 'verification-' . $chef->id . '.' . pathinfo($chef->verification_docs, PATHINFO_EXTENSION));
    }

    public function bookings() {
        return [
            'data' => Booking::with(['chef.user', 'customer', 'service', 'payments', 'review'])->latest()->limit(200)->get()->map(fn(Booking $booking) => $booking->data())
        ];
    }

    public function bookingStatus(Request $request, $id, BookingService $bookingService) {
        $request->validate(['status' => 'required|in:confirmed,completed,cancelled']);
        return $bookingService->change(Booking::findOrFail($id), $request->status, 'admin')->data();
    }

    public function payments() {
        return ['data' => Payment::with('booking:id,booking_ref')->latest()->limit(200)->get()];
    }

    public function refund(Request $request, $id) {
        DB::transaction(function () use ($id) {
            $payment = Payment::findOrFail($id);
            $booking = Booking::lockForUpdate()->findOrFail($payment->booking_id);
            $payment = Payment::lockForUpdate()->findOrFail($id);
            abort_unless($payment->status === 'success' && $booking->status === 'cancelled', 422, 'Only successful payments for cancelled bookings may be refunded.');
            $payment->update(['status' => 'refunded']);
        });
        return ['message' => 'Simulated refund recorded.'];
    }

    public function reviews() {
        return ['data' => Review::with('customer')->latest()->limit(200)->get()->map(fn(Review $review) => $review->data())];
    }

    public function deleteReview($id) {
        DB::transaction(function () use ($id) {
            $request = Review::findOrFail($id);
            $chef = Chef::lockForUpdate()->findOrFail($request->chef_id);
            $request->delete();
            $chef->recalculateRating();
        });
        return ['message' => 'Review removed.'];
    }

    public function activity() {
        $bookings = Booking::with('customer')->latest('updated_at')->limit(30)->get();
        return [
            'data' => $bookings->map(function (Booking $booking) {
                return [
                    'id' => $booking->id,
                    'action' => 'Booking ' . $booking->status,
                    'description' => $booking->booking_ref,
                    'user' => $booking->customer->name,
                    'created_at' => $booking->updated_at->toIso8601String()
                ];
            })
        ];
    }

    public function revenue() {
        return [
            'data' => collect(range(5, 0))->map(function ($offset) {
                $month = now()->subMonthsNoOverflow($offset);
                $amount = Payment::where('status', 'success')->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount');
                return ['month' => $month->format('M Y'), 'amount' => (float)$amount];
            })
        ];
    }

    public function contacts() {
        return ['data' => Contact::latest()->limit(200)->get()];
    }

    public function readContact($id) {
        Contact::findOrFail($id)->update(['is_read' => true]);
        return ['message' => 'Marked as read.'];
    }
}
