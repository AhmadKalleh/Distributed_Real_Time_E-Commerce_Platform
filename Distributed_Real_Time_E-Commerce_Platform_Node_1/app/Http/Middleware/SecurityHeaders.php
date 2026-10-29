<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security headers to every response.
 */
class SecurityHeaders
{
    /**
     * The Swagger UI page loads its assets from jsDelivr, so it gets a looser policy.
     */
    private const DOCS_CSP = "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        ."style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; "
        ."connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    private const API_CSP = "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $headers->set('Content-Security-Policy', $request->is('docs', 'docs/*') ? self::DOCS_CSP : self::API_CSP);
        $headers->remove('X-Powered-By');

        // Responses to authenticated calls may contain private data: never cache them.
        if ($request->is('api/*') && $request->bearerToken()) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
