<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CapturePaystackWebhookBody
{
    /**
     * Capture raw request body for Paystack webhook before any other middleware/controller consumes it.
     * Paystack signs the raw body; we need it for signature verification and parsing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();
        if ($content !== false && $content !== '') {
            $request->attributes->set('paystack_raw_payload', $content);
        }

        return $next($request);
    }
}
