<?php

namespace App\Services;
use Illuminate\Support\Facades\Mail;
class Notify {
    public static function send(string $email, string $subject, string $message): void {
        try {
            Mail::raw($message, fn($m) => $m->to($email)->subject('ChefLagbe | ' . $subject));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
