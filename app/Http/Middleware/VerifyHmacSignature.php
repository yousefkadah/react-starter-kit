<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return response()->json([
                'message' => 'Missing HMAC signature.',
            ], 401);
        }

        $secret = (string) config('passkit.api.hmac_secret', config('app.key'));

        if (Str::startsWith($secret, 'base64:')) {
            $decoded = base64_decode(Str::after($secret, 'base64:'), true);
            if ($decoded !== false) {
                $secret = $decoded;
            }
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json([
                'message' => 'Invalid HMAC signature.',
            ], 401);
        }

        return $next($request);
    }
}
