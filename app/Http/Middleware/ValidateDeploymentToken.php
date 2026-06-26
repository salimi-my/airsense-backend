<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDeploymentToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Deployment-Token');
        $expectedToken = config('app.deployment_token');

        if (! $token || ! $expectedToken || ! hash_equals((string) $expectedToken, (string) $token)) {
            return response()->json([
                'message' => 'Unauthorized. Invalid deployment token.',
            ], 401);
        }

        return $next($request);
    }
}
