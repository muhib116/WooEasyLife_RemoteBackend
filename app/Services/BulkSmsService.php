<?php

namespace App\Services;

use App\LogHelper;
use Illuminate\Support\Facades\Http;

class BulkSmsService
{
    /**
 * @return array{ok: bool, response_code: int|null, message_id: mixed, message: string|null, raw: string|null}
 */
public function send(string $number, string $message): array
{
        $apiKey = config('services.bulksms.api_key');
        $senderId = config('services.bulksms.sender_id');

        if (! filled($apiKey) || ! filled($senderId)) {
            return [
                'ok' => false,
                'response_code' => null,
                'message_id' => null,
                'message' => 'SMS provider is not configured.',
                'raw' => null,
            ];
        }

        $number = $this->normalizeNumber($number);
        $type = $this->containsNonAscii($message) ? 'unicode' : 'text';

        try {
            // BulkSMSBD accepts GET/POST; POST matches the working merchant SMS path.
            $response = Http::timeout(20)
                ->asForm()
                ->post('http://bulksmsbd.net/api/smsapi', [
                    'api_key' => $apiKey,
                    'type' => $type,
                    'number' => $number,
                    'senderid' => $senderId,
                    'message' => $message,
                ]);

            $raw = $response->body();
            $payload = $response->json();

            if (! is_array($payload)) {
                LogHelper::saveLog('bulksms invalid response', $raw);

                return [
                    'ok' => false,
                    'response_code' => null,
                    'message_id' => null,
                    'message' => 'Invalid SMS provider response.',
                    'raw' => $raw,
                ];
            }

            $code = isset($payload['response_code']) ? (int) $payload['response_code'] : null;
            $error = trim((string) ($payload['error_message'] ?? ''));
            $success = trim((string) ($payload['success_message'] ?? ''));
            $messageId = $payload['message_id'] ?? null;

            // HTTP 200 does not mean submitted — only response_code 202 does.
            $ok = $code === 202 && filled($messageId) && $error === '';

            if (! $ok) {
                LogHelper::saveLog('bulksms send failed', $raw);
            }

            return [
                'ok' => $ok,
                'response_code' => $code,
                'message_id' => $messageId,
                'message' => $ok
                    ? ($success !== '' ? $success : 'SMS Submitted Successfully')
                    : ($error !== '' ? $error : 'SMS send failed.'),
                'raw' => $raw,
            ];
        } catch (\Throwable $th) {
            LogHelper::saveLog('bulksms send exception', $th->getMessage());

            return [
                'ok' => false,
                'response_code' => null,
                'message_id' => null,
                'message' => $th->getMessage(),
                'raw' => null,
            ];
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('services.bulksms.api_key'))
            && filled(config('services.bulksms.sender_id'));
    }

    private function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            return $digits;
        }

        if (strlen($digits) === 13 && str_starts_with($digits, '8801')) {
            return '0'.substr($digits, 2);
        }

        return $digits !== '' ? $digits : $number;
    }

    private function containsNonAscii(string $message): bool
    {
        return (bool) preg_match('/[^\x00-\x7F]/', $message);
    }
}
