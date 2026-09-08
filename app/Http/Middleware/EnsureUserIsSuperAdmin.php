<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'This action requires superadmin privileges.'], 403);
        }

        return $next($request);
    }
}
