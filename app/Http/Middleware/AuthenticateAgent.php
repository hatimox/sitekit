<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'No authentication token provided'], 401);
        }

        $server = Server::where('agent_token', $token)
            ->where('status', '!=', Server::STATUS_FAILED)
            ->first();

        if (!$server) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Check if token has expired (only for provisioning tokens)
        if ($server->agent_token_expires_at && $server->agent_token_expires_at->isPast()) {
            return response()->json(['error' => 'Token has expired'], 401);
        }

        // Attach server to request for use in controllers
        $request->merge(['server' => $server]);
        $request->setUserResolver(fn () => $server);

        return $next($request);
    }
}
