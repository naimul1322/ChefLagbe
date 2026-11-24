<?php

namespace App\Http\Controllers;
use App\Models\{User, Chef};
use App\Services\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, DB, Password};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
class AuthController {
    private function response(Request $request, User $user, int $status = 200) {
        $data = ['user' => $user->publicData()];
        if ($request->hasSession() && !$request->filled('device_name')) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();
        } else {
            $data['token'] = $user->createToken(substr((string)$request->input('device_name', 'api'), 0, 100))->plainTextToken;
        }
        return response()->json($data, $status);
    }

    public function signup(Request $request) {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:30',
            'location' => 'required|string|max:120',
            'password' => [
                'bail',
                'required',
                'string',
                'confirmed',
                'max:72',
                PasswordRule::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72) {
                        $fail('Password must not exceed 72 bytes.');
                    }
                }
            ],
            'user_type' => 'required|in:customer,chef',
            'terms' => 'accepted'
        ]);
        $data['email'] = strtolower($data['email']);
        unset($data['terms']);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            if ($user->user_type === 'chef') {
                Chef::create(['user_id' => $user->id, 'availability' => []]);
            }
            return $user;
        });
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }
        Notify::send($user->email, 'Welcome', 'Welcome to ChefLagbe. This is a development version.');
        return $this->response($request, $user, 201);
    }

    public function login(Request $request) {
        return $this->authenticate($request, false);
    }

    public function adminLogin(Request $request) {
        return $this->authenticate($request, true);
    }

    private function authenticate(Request $request, bool $admin) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|max:72',
            'device_name' => 'sometimes|string|max:100'
        ]);
        $user = User::where('email', strtolower($request->email))->first();
        $valid = strlen($request->password) <= 72 && Hash::check($request->password, $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        abort_unless($user && $valid && $user->is_active && (($user->user_type === 'admin') === $admin), 422, 'Invalid credentials or inactive account.');
        return $this->response($request, $user);
    }

    public function resendVerification(Request $request) {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return ['message' => 'Email is already verified.'];
        }
        $user->sendEmailVerificationNotification();
        return ['message' => 'Verification email requested. Check your inbox or the configured mail log.'];
    }

    public function me(Request $request) {
        return ['user' => $request->user()->publicData()];
    }

    public function logout(Request $request) {
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return ['message' => 'Signed out.'];
    }

    public function forgot(Request $request) {
        $request->validate(['email' => 'required|email']);
        try {
            Password::sendResetLink(['email' => strtolower($request->email)]);
        } catch (\Throwable $e) {
            report($e);
        }
        return ['message' => 'If the account exists, a password reset link will be sent.'];
    }

    public function reset(Request $request) {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => [
                'bail',
                'required',
                'string',
                'confirmed',
                'max:72',
                PasswordRule::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72) {
                        $fail('Password must not exceed 72 bytes.');
                    }
                }
            ]
        ]);
        $data['password_confirmation'] = $request->input('password_confirmation');
        $status = Password::reset($data, function (User $user, $newPassword) {
            $user->forceFill(['password' => Hash::make($newPassword), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new \Illuminate\Auth\Events\PasswordReset($user));
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, 'Invalid or expired reset link.');
        return ['message' => 'Password updated. Sign in again.'];
    }
}
