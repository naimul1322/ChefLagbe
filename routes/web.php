<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
foreach ([
    '' => 'home',
    'about' => 'about',
    'services' => 'services',
    'chefs' => 'chefs',
    'contact' => 'contact',
    'login' => 'login',
    'signup' => 'signup',
    'forgot-password' => 'forgot-password',
    'privacy' => 'privacy',
    'terms' => 'terms',
    'admin/login' => 'admin-login'
] as $uri => $page) {
    Route::get('/' . $uri, fn() => view('layout', ['page' => $page, 'title' => ucwords(str_replace('-', ' ', $page))]));
}
Route::get('/reset-password/{token}', fn(Request $r, $token) => view('layout', [
    'page' => 'reset-password',
    'title' => 'Reset password',
    'params' => ['token' => $token, 'email' => $r->query('email')]
]))->name('password.reset');
Route::get('/chefs/{id}', fn($id) => view('layout', [
    'page' => 'profile',
    'title' => 'Chef profile',
    'params' => ['id' => (int)$id]
]))->whereNumber('id');
Route::get('/booking/{id}', fn($id) => view('layout', [
    'page' => 'booking',
    'title' => 'Book your chef',
    'params' => ['id' => (int)$id]
]))->whereNumber('id');
Route::get('/payment/{id}', fn($id) => view('layout', [
    'page' => 'payment',
    'title' => 'Payment',
    'params' => ['id' => (int)$id]
]))->whereNumber('id')->middleware(['auth', 'auth.session', 'active', 'role:customer']);
Route::get('/dashboard', fn() => view('layout', ['page' => 'dashboard', 'title' => 'Dashboard']))->middleware(['auth', 'auth.session', 'active', 'role:customer,chef']);
Route::get('/admin/dashboard', fn() => view('layout', ['page' => 'admin-dashboard', 'title' => 'Admin dashboard']))->middleware(['auth', 'auth.session', 'active', 'role:admin']);
Route::get('/email/verify/{id}/{hash}', function (Request $r, $id, $hash) {
    $u = \App\Models\User::findOrFail($id);
    abort_unless(hash_equals(sha1($u->getEmailForVerification()), $hash), 403);
    if (!$u->hasVerifiedEmail()) {
        $u->markEmailAsVerified();
    }
    return redirect('/login')->with('status', 'Email verified.');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
