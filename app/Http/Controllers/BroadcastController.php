<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastController extends Controller
{
    public function authenticate(Request $request)
    {
        try {
            if (! $request->user()) {
                Log::warning('Broadcast authorization failed: user not authenticated');

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Ensure channel_name and socket_id are available
            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');

            // If not found, try parsing from raw content (Pusher sends form-encoded)
            if (! $channelName || ! $socketId) {
                $rawContent = $request->getContent();
                if ($rawContent) {
                    parse_str($rawContent, $parsed);
                    $channelName = $channelName ?? $parsed['channel_name'] ?? null;
                    $socketId = $socketId ?? $parsed['socket_id'] ?? null;

                    if ($channelName && $socketId) {
                        $request->merge([
                            'channel_name' => $channelName,
                            'socket_id' => $socketId,
                        ]);
                    }
                }
            }

            if (! $channelName || ! $socketId) {
                Log::error('Broadcast authorization failed: missing channel_name or socket_id', [
                    'has_channel' => (bool) $channelName,
                    'has_socket' => (bool) $socketId,
                    'content_type' => $request->header('Content-Type'),
                    'raw_content' => substr($request->getContent(), 0, 200),
                ]);

                return response()->json(['error' => 'Missing required parameters'], 400);
            }

            $response = Broadcast::auth($request);

            if (! $response) {
                Log::error('Broadcast authorization failed: no response from Broadcast::auth()', [
                    'channel' => $channelName,
                    'socket_id' => $socketId,
                    'user_id' => $request->user()?->uuid,
                ]);

                return response()->json(['error' => 'Authorization failed'], 500);
            }

            // Broadcast::auth() can return either a Response object or an array
            if (is_array($response)) {
                // If it's an array, return it as JSON (this is the auth data)
                return response()->json($response);
            }

            // If it's a Response object, check status code
            if ($response->getStatusCode() >= 400) {
                Log::warning('Broadcast authorization failed', [
                    'channel' => $channelName,
                    'socket_id' => $socketId,
                    'user_id' => $request->user()?->uuid,
                    'status' => $response->getStatusCode(),
                    'response' => $response->getContent(),
                ]);

                return response()->json(['error' => 'Forbidden'], 403);
            }

            $content = $response->getContent();
            if (empty($content)) {
                Log::error('Broadcast authorization returned empty response', [
                    'channel' => $channelName,
                    'socket_id' => $socketId,
                    'user_id' => $request->user()?->uuid,
                ]);

                return response()->json(['error' => 'Authorization failed'], 500);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Broadcast authorization exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'channel' => $request->input('channel_name'),
                'socket_id' => $request->input('socket_id'),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Authorization failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
