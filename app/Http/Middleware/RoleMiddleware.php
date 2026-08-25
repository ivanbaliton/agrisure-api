<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $role
    ): Response {

        $allowedRoles = explode(',', $role);

        if (
            !$request->user() ||
            !in_array($request->user()->role, $allowedRoles, true)
        ) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        return $next($request);
    }
}