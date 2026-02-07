<?php

namespace App\Services\Notification;

use App\Models\Notification as NotificationModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    public function isConfigured(): bool
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        return ! empty($sid) && ! empty($token) && ! empty($from);
    }

    /**
     * Send in-app notification content to WhatsApp. Number must be E.164 (e.g. +32490123456).
     */
    public function sendNotification(NotificationModel $notification, string $toNumber): bool
    {
        if (! $this->isConfigured()) {
            Log::debug('WhatsApp not configured, skipping send');

            return false;
        }

        $to = $this->normalizeNumber($toNumber);
        if (! $to) {
            Log::warning('WhatsApp: invalid number', ['to' => $toNumber]);

            return false;
        }

        $body = $this->formatMessage($notification);
        $from = config('services.twilio.whatsapp_from');
        if (! str_starts_with($from, 'whatsapp:')) {
            $from = 'whatsapp:'.$from;
        }
        $to = str_starts_with($to, 'whatsapp:') ? $to : 'whatsapp:'.$to;

        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            config('services.twilio.account_sid')
        );

        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.twilio.account_sid'), config('services.twilio.auth_token'))
                ->post($url, [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $body,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp send failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function normalizeNumber(string $number): ?string
    {
        $number = preg_replace('/[\s\-\(\)]/', '', $number);
        if (! preg_match('/^\+?[0-9]{10,15}$/', $number)) {
            return null;
        }
        if (! str_starts_with($number, '+')) {
            $number = '+'.$number;
        }

        return $number;
    }

    private function formatMessage(NotificationModel $n): string
    {
        $lines = [
            "🔔 *{$n->title}*",
            '',
            $n->message,
        ];
        if ($n->description) {
            $lines[] = '';
            $lines[] = $n->description;
        }
        if ($n->action_url) {
            $url = str_starts_with($n->action_url, 'http')
                ? $n->action_url
                : rtrim(config('app.frontend_url'), '/').'/'.ltrim($n->action_url, '/');
            $lines[] = '';
            $lines[] = '👉 '.$url;
        }

        return implode("\n", $lines);
    }
}
