<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RequireRole {
    public function handle(Request $request, Closure $next, string...$roles) {
        abort_unless($request->user() && in_array($request->user()->user_type, $roles, true), 403, 'You do not have permission to perform this action.');
        return $next($request);
    }
}
