<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotificationService
{
    public function isConfigured(): bool
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        return ! empty($token) && ! empty($channel);
    }

    public function sendErrorNotification(\Throwable $exception, ?\Illuminate\Http\Request $request = null): bool
    {
        if (! $this->isConfigured()) {
            Log::debug('Slack not configured, skipping error notification');

            return false;
        }

        $message = $this->formatErrorMessage($exception, $request);

        try {
            $response = Http::withToken(config('services.slack.notifications.bot_user_oauth_token'))
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => config('services.slack.notifications.channel'),
                    'text' => '🚨 *Application Error*',
                    'blocks' => $message,
                ]);

            if (! $response->successful() || ! ($response->json()['ok'] ?? false)) {
                Log::warning('Slack error notification failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Slack error notification exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function formatErrorMessage(\Throwable $exception, ?\Illuminate\Http\Request $request): array
    {
        $environment = config('app.env');
        $appName = config('app.name');
        $exceptionClass = get_class($exception);
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();
        $tracePreview = substr($trace, 0, 1000);

        $url = $request ? $request->fullUrl() : 'N/A';
        $method = $request ? $request->method() : 'N/A';
        $ip = $request ? $request->ip() : 'N/A';
        $userAgent = $request ? $request->userAgent() : 'N/A';
        $userId = $request && $request->user() ? $request->user()->id : 'N/A';

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '🚨 Application Error',
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Environment:*\n{$environment}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Application:*\n{$appName}",
                    ],
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Exception:*\n`{$exceptionClass}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Message:*\n{$message}",
                    ],
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*File:*\n`{$file}:{$line}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*User ID:*\n{$userId}",
                    ],
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*URL:*\n{$method} {$url}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*IP:*\n{$ip}",
                    ],
                ],
            ],
        ];

        if ($tracePreview) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Stack Trace (first 1000 chars):*\n```{$tracePreview}```",
                ],
            ];
        }

        return $blocks;
    }
}
