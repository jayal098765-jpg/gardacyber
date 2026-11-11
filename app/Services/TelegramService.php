<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramService
{
    /**
     * @param  array{
     *     signal?: ?string,
     *     asset?: ?string,
     *     bot_token?: ?string,
     *     chat_id?: ?string,
     *     webhook_url?: ?string
     * }  $options
     * @return array{
     *     success: bool,
     *     message: string,
     *     delivered?: bool,
     *     formatted_text: string,
     *     payload: array<string, mixed>
     * }
     */
    public function sendTestAlert(User $user, array $options = []): array
    {
        $signal = $options['signal'] ?? 'Defacement / Gambling Signal';
        $asset = $options['asset'] ?? 'training.gardacyber.test';
        $severity = 'CRITICAL';
        $time = now('UTC')->toDateTimeString().' UTC';

        $markdown = "*🚨 GardaCyber Security Alert*\n\n"
            ."*Signal:* {$signal}\n"
            ."*Asset:* `{$asset}`\n"
            ."*Severity:* {$severity}\n"
            ."*Detected at:* {$time}\n\n"
            ."*Summary:* Active perimeter alteration observed with malicious gambling or defacement signatures.\n"
            ."*Action Required:* Immediate host isolation and webroot integrity verification recommended.\n\n"
            .'_Simulated alert dispatched from GardaCyber._';

        $payload = [
            'event' => 'security_alert_test',
            'signal' => $signal,
            'asset' => $asset,
            'severity' => strtolower($severity),
            'detected_at' => $time,
            'markdown' => $markdown,
        ];

        $botToken = $options['bot_token'] ?? $user->telegram_bot_token ?? config('services.telegram.bot_token');
        $chatId = $options['chat_id'] ?? $user->telegram_chat_id ?? config('services.telegram.chat_id');
        $webhookUrl = $options['webhook_url'] ?? $user->telegram_webhook_url ?? config('services.telegram.webhook_url');

        if (! $webhookUrl && (! $botToken || ! $chatId)) {
            return [
                'success' => false,
                'message' => 'Telegram credentials unconfigured. Bot token and chat ID or webhook URL required.',
                'formatted_text' => $markdown,
                'payload' => $payload,
            ];
        }

        try {
            if ($webhookUrl) {
                $response = Http::timeout(10)->connectTimeout(5)->post($webhookUrl, [
                    'text' => $markdown,
                    'parse_mode' => 'Markdown',
                    'payload' => $payload,
                ]);
            } else {
                $response = Http::timeout(10)->connectTimeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $markdown,
                    'parse_mode' => 'Markdown',
                ]);
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'delivered' => true,
                    'message' => 'Simulated security alert dispatched to Telegram.',
                    'formatted_text' => $markdown,
                    'payload' => $payload,
                ];
            }

            return [
                'success' => false,
                'message' => 'Telegram API error: '.$response->body(),
                'formatted_text' => $markdown,
                'payload' => $payload,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Telegram dispatch failed: '.$e->getMessage(),
                'formatted_text' => $markdown,
                'payload' => $payload,
            ];
        }
    }
}
