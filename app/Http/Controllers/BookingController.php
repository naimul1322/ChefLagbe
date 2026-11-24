<?php

namespace App\Http\Controllers;
use App\Models\{Booking, Chef, Review, Payment};
use App\Services\{BookingService, Notify};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class BookingController {
    private function visible(Request $request) {
        $user = $request->user();
        $query = Booking::with(['chef.user', 'customer', 'service', 'payments', 'review']);
        return match ($user->user_type) {
            'customer' => $query->where('customer_id', $user->id),
            'chef' => $query->where('chef_id', $user->chef()->value('id') ?? 0),
            'admin' => $query,
            default => $query->whereRaw('1=0')
        };
    }

    public function index(Request $request) {
        return [
            'data' => $this->visible($request)->latest()->limit(150)->get()->map(fn(Booking $booking) => $booking->data())
        ];
    }

    public function show(Request $request, $id) {
        return $this->visible($request)->findOrFail($id)->data();
    }

    public function store(Request $request, BookingService $bookingService) {
        $data = $request->validate([
            'chef_id' => 'required|integer|exists:chefs,id',
            'service_id' => 'required|integer|exists:services,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'booking_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|integer|min:1|max:12',
            'guests' => 'required|integer|min:1|max:300',
            'cuisine' => 'nullable|string|max:120',
            'dietary_notes' => 'nullable|string|max:1000',
            'special_instructions' => 'required|string|max:1500'
        ]);
        $booking = $bookingService->create($request->user(), $data);
        Notify::send($booking->chef->user->email, 'New booking request', $booking->booking_ref . ' is awaiting simulated payment and confirmation.');
        Notify::send($request->user()->email, 'Booking request created', $booking->booking_ref . ' is pending.');
        return response()->json($booking->data(), 201);
    }

    public function update(Request $request, $id, BookingService $bookingService) {
        $request->validate(['status' => 'required|in:confirmed,completed,cancelled']);
        $booking = $this->visible($request)->findOrFail($id);
        $booking = $bookingService->change($booking, $request->status, $request->user()->user_type);
        Notify::send($booking->customer->email, 'Booking ' . $booking->status, $booking->booking_ref . ' status: ' . $booking->status . '.');
        if ($booking->status === 'completed') {
            Notify::send($booking->customer->email, 'Leave a review', 'Share your experience for booking ' . $booking->booking_ref . '.');
        }
        return $booking->data();
    }

    public function cancel(Request $request, $id, BookingService $bookingService) {
        $booking = $this->visible($request)->findOrFail($id);
        return $bookingService->change($booking, 'cancelled', $request->user()->user_type)->data();
    }

    public function availability(Request $request, BookingService $bookingService) {
        $data = $request->validate([
            'chef_id' => 'required|integer',
            'booking_date' => 'required|date_format:Y-m-d',
            'booking_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|integer|min:1|max:12'
        ]);
        return [
            'available' => $bookingService->available(Chef::listed()->findOrFail($data['chef_id']), $data['booking_date'], $data['booking_time'], $data['duration_hours'])
        ];
    }

    public function payment(Request $request, BookingService $bookingService) {
        $data = $request->validate([
            'booking_id' => 'required|integer',
            'method' => 'required|in:card,bkash,nagad,rocket,cash',
            'outcome' => 'required|in:success,failed'
        ]);
        $booking = $this->visible($request)->findOrFail($data['booking_id']);
        $payment = DB::transaction(function () use ($booking, $data, $bookingService) {
            $chef = Chef::lockForUpdate()->findOrFail($booking->chef_id);
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $existing = $booking->payments()->where('status', 'success')->first();
            if ($existing) {
                return $existing;
            }
            abort_unless($booking->status === 'pending', 422, 'Only pending bookings may be paid.');
            abort_unless($chef->is_verified && $chef->user->is_active && $bookingService->available($chef, $booking->booking_date->format('Y-m-d'), substr($booking->booking_time, 0, 5), $booking->duration_hours, $booking->id), 422, 'This slot is no longer available. Do not pay; request another booking.');
            $paymentRecord = $booking->payments()->create([
                'method' => $data['method'],
                'amount' => $booking->total_amount,
                'transaction_id' => 'DEMO-' . Str::upper(Str::random(16)),
                'status' => $data['outcome'],
                'paid_at' => $data['outcome'] === 'success' ? now(): null
            ]);
            return $paymentRecord;
        });
        if ($payment->status === 'success') {
            Notify::send($request->user()->email, 'Simulated payment receipt', $payment->transaction_id . ' — BDT ' . $payment->amount . '. No money was charged.');
        }
        return response()->json([
            'payment' => $payment,
            'booking' => $booking->fresh()->data(),
            'message' => $payment->status === 'success' ? 'Simulated payment successful. Await chef confirmation.': 'Simulated payment failed; you may retry.'
        ], 201);
    }

    public function verifyPayment(Request $request) {
        $request->validate(['transaction_id' => 'required|string|max:50']);
        $paymentRecord = Payment::where('transaction_id', $request->transaction_id)->firstOrFail();
        $this->visible($request)->findOrFail($paymentRecord->booking_id);
        return ['payment' => $paymentRecord];
    }

    public function payments(Request $request, $id) {
        $booking = $this->visible($request)->findOrFail($id);
        return ['data' => $booking->payments()->latest()->get()];
    }

    public function review(Request $request) {
        $data = $request->validate([
            'booking_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1500'
        ]);
        $booking = $this->visible($request)->findOrFail($data['booking_id']);
        $review = DB::transaction(function () use ($booking, $data, $request) {
            $chef = Chef::lockForUpdate()->findOrFail($booking->chef_id);
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            abort_unless($booking->status === 'completed' && !$booking->review()->exists(), 422, 'Only one review is allowed per completed booking.');
            $newReview = Review::create([
                'booking_id' => $booking->id,
                'customer_id' => $request->user()->id,
                'chef_id' => $booking->chef_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null
            ]);
            $chef->recalculateRating();
            return $newReview;
        });
        return response()->json($review->data(), 201);
    }

    public function myReviews(Request $request) {
        $user = $request->user();
        $query = Review::with('customer');
        if ($user->user_type === 'chef') {
            $query->where('chef_id', $user->chef()->value('id') ?? 0);
        }
        else $query->where('customer_id', $user->id);
        return ['data' => $query->latest()->get()->map(fn(Review $review) => $review->data())];
    }
}
