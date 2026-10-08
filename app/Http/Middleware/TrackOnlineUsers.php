<?php

namespace App\Http\Middleware;

use App\Support\OnlineUsers;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records "last seen" for signed-in users when sessions do not live in the
 * database (e.g. Redis), so the dashboard can still show who is online.
 */
class TrackOnlineUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && OnlineUsers::tracking()) {
            OnlineUsers::touch((string) $request->user()->id);
        }

        return $response;
    }
}
