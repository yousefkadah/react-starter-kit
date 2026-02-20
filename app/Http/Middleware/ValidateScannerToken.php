<?php

namespace App\Http\Middleware;

use App\Models\ScannerLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateScannerToken
{
    /**
     * Handle an incoming request.
     *
     * Authenticates scanner requests using a token from the route parameter
     * or X-Scanner-Token header and sets the scanner context on the request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token') ?? $request->header('X-Scanner-Token');

        if (! $token) {
            abort(401, 'Scanner token is required.');
        }

        $scannerLink = ScannerLink::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $scannerLink) {
            abort(401, 'Invalid or inactive scanner token.');
        }

        $scannerLink->markAsUsed();

        $request->attributes->set('scanner_link', $scannerLink);
        $request->attributes->set('scanner_user_id', $scannerLink->user_id);

        return $next($request);
    }
}
