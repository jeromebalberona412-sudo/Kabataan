<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SK_FED, User::ROLE_SK_OFFICIAL], true)) {
            abort(403);
        }

        return $next($request);
    }
}
