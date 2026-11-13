<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controleert het apparaat-token dat de agents (laptop-script, browser-extensie,
 * telefoon-script) meesturen als "Authorization: Bearer <token>".
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = Device::findByToken($request->bearerToken());

        if (! $device) {
            return response()->json(['message' => 'Ongeldig apparaat-token.'], 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
