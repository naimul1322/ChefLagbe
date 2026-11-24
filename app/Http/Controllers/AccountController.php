<?php

namespace App\Http\Controllers;
use App\Models\{Chef};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage, DB};
use Illuminate\Validation\Rules\Password;
class AccountController {
    public function profile(Request $request) {
        return [
            'user' => $request->user()->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'location',
                'user_type',
                'avatar',
                'email_verified_at'
            ])
        ];
    }

    public function update(Request $request) {
        $data = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'phone' => 'required|string|max:30',
            'location' => 'required|string|max:120'
        ]);
        $request->user()->update($data);
        return $this->profile($request);
    }

    public function changePassword(Request $request) {
        $request->validate([
            'current_password' => 'required|string|max:72',
            'password' => [
                'bail',
                'required',
                'string',
                'confirmed',
                'max:72',
                Password::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72) {
                        $fail('Password must not exceed 72 bytes.');
                    }
                }
            ]
        ]);
        $user = $request->user();
        abort_unless(strlen($request->current_password) <= 72 && Hash::check($request->current_password, $user->password), 422, 'Incorrect current password.');
        $user->forceFill(['password' => Hash::make($request->password), 'remember_token' => \Illuminate\Support\Str::random(60)])->save();
        $user->tokens()->delete();
        if ($request->hasSession()) {
            $request->session()->forget('password_hash_web');
            $request->session()->regenerate();
        }
        return [
            'message' => 'Password changed.',
            'csrf_token' => $request->hasSession() ? $request->session()->token(): null
        ];
    }

    public function avatar(Request $request) {
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        if (!$path) {
            abort(500, 'File could not be saved.');
        }
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->update(['avatar' => $path]);
        return ['message' => 'Avatar uploaded.'];
    }

    public function chef(Request $request) {
        $chef = $request->user()->chef()->firstOrFail();
        return ['chef' => array_merge($chef->publicData(), ['has_documents' => (bool)$chef->verification_docs])];
    }

    public function updateChef(Request $request) {
        $data = $request->validate([
            'specialty' => 'required|string|max:180',
            'bio' => 'required|string|max:2000',
            'price_per_hour' => 'required|numeric|min:100|max:100000|decimal:0,2',
            'experience_years' => 'required|integer|min:0|max:70'
        ]);
        $chef = $request->user()->chef()->firstOrFail();
        $chef->update($data);
        return ['message' => 'Chef profile saved.', 'chef' => $chef->publicData()];
    }

    public function schedule(Request $request) {
        $data = $request->validate([
            'availability' => 'present|array|max:7',
            'availability.*' => 'array:start,end',
            'availability.*.start' => 'required|date_format:H:i',
            'availability.*.end' => 'required|date_format:H:i'
        ]);
        $schedule = [];
        foreach ($data['availability'] as $day => $slot) {
            abort_unless(in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true) && $slot['end'] > $slot['start'], 422, 'Invalid availability schedule.');
            $schedule[$day] = $slot;
        }
        DB::transaction(function () use ($request, $schedule) {
            $chef = $request->user()->chef()->lockForUpdate()->firstOrFail();
            $chef->update(['availability' => $schedule]);
        });
        return ['message' => 'Availability updated.'];
    }

    public function apply(Request $request) {
        $request->validate([
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'verification_docs' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);
        $chef = $request->user()->chef()->firstOrFail();
        $changed = false;
        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('chefs', 'public');
            abort_unless($path, 500, 'Image upload failed.');
            if ($chef->profile_image) {
                Storage::disk('public')->delete($chef->profile_image);
            }
            $chef->profile_image = $path;
            $changed = true;
        }
        if ($request->hasFile('verification_docs')) {
            $path = $request->file('verification_docs')->store('verification', 'local');
            abort_unless($path, 500, 'Document upload failed.');
            if ($chef->verification_docs) {
                Storage::disk('local')->delete($chef->verification_docs);
            }
            $chef->verification_docs = $path;
            $chef->is_verified = false;
            $changed = true;
        }
        $chef->save();
        abort_unless($changed, 422, 'Select at least one file.');
        return ['message' => 'Files saved. Verification documents are private and subject to admin approval.'];
    }

    public function earnings(Request $request) {
        $chef = $request->user()->chef()->firstOrFail();
        $b = $chef->bookings()->where('status', 'completed')->whereHas('payments', fn($p) => $p->where('status', 'success'));
        return [
            'completed' => $b->count(),
            'earnings' => (float)$b->sum('subtotal'),
            'reviews' => $chef->total_reviews,
            'rating' => (float)$chef->rating
        ];
    }
}
