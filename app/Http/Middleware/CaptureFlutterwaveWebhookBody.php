<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureFlutterwaveWebhookBody
{
    /**
     * Capture raw request body for Flutterwave webhook before any other middleware/controller consumes it.
     * Flutterwave signs the raw body; we need it for signature verification and parsing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();
        if ($content !== false && $content !== '') {
            $request->attributes->set('flutterwave_raw_payload', $content);
        }

        return $next($request);
    }
}
