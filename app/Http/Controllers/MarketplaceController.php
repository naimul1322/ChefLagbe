<?php

namespace App\Http\Controllers;
use App\Models\{Chef, Service, Booking, User, Review, Contact};
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MarketplaceController {
    public function chefs(Request $request) {
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'cuisine' => 'nullable|string|max:80',
            'location' => 'nullable|string|max:120',
            'rating' => 'nullable|numeric|min:0|max:5',
            'max_price' => 'nullable|numeric|min:0|max:100000',
            'per_page' => 'nullable|integer|min:1|max:24',
            'page' => 'nullable|integer|min:1|max:100000'
        ]);
        $query = Chef::listed()->with('user');
        if (!empty($data['q'])) {
            $search = '%' . $data['q'] . '%';
            $fullNameExpr = DB::connection()->getDriverName() === 'sqlite'
                ? "(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))"
                : "CONCAT_WS(' ', first_name, last_name)";
            $query->where(function ($chefQuery) use ($search, $fullNameExpr) {
                $chefQuery->where('specialty', 'like', $search)
                    ->orWhere('bio', 'like', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search, $fullNameExpr) {
                        $userQuery->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhereRaw("{$fullNameExpr} LIKE ?", [$search])
                            ->orWhere('location', 'like', $search);
                    });
            });
        }
        if (!empty($data['cuisine'])) {
            $query->where('specialty', 'like', '%' . $data['cuisine'] . '%');
        }
        if (!empty($data['location'])) {
            $query->whereHas('user', fn($u) => $u->where('location', 'like', '%' . $data['location'] . '%'));
        }
        if (!empty($data['rating'])) {
            $query->where('rating', '>=', $data['rating']);
        }
        if (!empty($data['max_price'])) {
            $query->where('price_per_hour', '<=', $data['max_price']);
        }
        $results = $query->orderByDesc('rating')->orderBy('id')->paginate($data['per_page'] ?? 6);
        return $results->through(fn(Chef $c) => $c->publicData());
    }

    public function chef($id) {
        return Chef::listed()->with('user')->findOrFail($id)->publicData();
    }

    public function reviews($id) {
        $chef = Chef::listed()->findOrFail($id);
        return [
            'data' => $chef->reviews()->with('customer')->latest()->limit(30)->get()->map(fn(Review $review) => $review->data())
        ];
    }

    public function availability(Request $request, $id, BookingService $service) {
        $data = $request->validate([
            'booking_date' => 'required|date_format:Y-m-d',
            'booking_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|integer|min:1|max:12'
        ]);
        $chef = Chef::listed()->findOrFail($id);
        return [
            'available' => $service->available($chef, $data['booking_date'], $data['booking_time'], $data['duration_hours'])
        ];
    }

    public function services() {
        return ['data' => Service::where('is_active', true)->orderBy('id')->get()];
    }

    public function service($id) {
        return Service::where('is_active', true)->findOrFail($id);
    }

    public function stats() {
        return [
            'customers' => User::where('user_type', 'customer')->where('is_active', true)->count(),
            'chefs' => Chef::listed()->count(),
            'bookings' => Booking::where('status', 'completed')->count(),
            'locations' => User::where('is_active', true)->whereNotNull('location')->distinct()->count('location')
        ];
    }

    public function contact(Request $request) {
        $data = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'subject' => 'required|string|max:100',
            'message' => 'required|string|max:3000'
        ]);
        Contact::create($data);
        return response()->json(['message' => 'Your message has been received.'], 201);
    }
}
