<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyValidationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $apiKey = $request->header('x-api-key');

        $getApiKey = config('app.x_api_key');

        if ($apiKey !== $getApiKey) {
            return response()->json([
                'error' => 'invalid api key',
            ]);
        }

        return $next($request);
    }
}
