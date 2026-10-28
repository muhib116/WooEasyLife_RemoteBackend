<?php

namespace App\Services\Ads;

use App\Models\SubscriptionInquiry;
use App\Services\LandingSettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ChatGPT Ads Measurement Pixel companion.
 * Browser events use the public pixel id. The Conversions API key stays on the server.
 */
class OpenAiAdsConversions
{
    public const EVENTS_URL = 'https://bzr.openai.com/v1/events';

    public function __construct(private LandingSettingsService $settings) {}

    public function pixelId(): ?string
    {
        $id = trim((string) $this->settings->openaiAdsPixelId());

        return $id !== '' ? $id : null;
    }

    public function recordEnrollment(Request $request, SubscriptionInquiry $inquiry, bool $isFreeTrial): void
    {
        $pixelId = $this->pixelId();
        $apiKey = $this->settings->openaiAdsApiKey();

        if ($pixelId === null || $apiKey === null || $apiKey === '') {
            return;
        }

        $event = $this->enrollmentEvent($request, $inquiry, $isFreeTrial);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(5)
                ->post(self::EVENTS_URL.'?pid='.rawurlencode($pixelId), [
                    'validate_only' => false,
                    'integration_source' => 'wooeasylife',
                    'events' => [$event],
                ]);

            if (! $response->successful()) {
                $this->logFailure($response, $event['type'], $event['id']);
            }
        } catch (\Throwable $e) {
            Log::warning('OpenAI Ads conversion was not sent.', [
                'event' => $event['type'],
                'event_id' => $event['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function enrollmentEvent(Request $request, SubscriptionInquiry $inquiry, bool $isFreeTrial): array
    {
        $eventId = 'inquiry_'.$inquiry->id;
        $planId = (string) $inquiry->package_hub_id;
        $data = [
            'type' => 'plan_enrollment',
            'plan_id' => $planId,
        ];

        if (! $isFreeTrial) {
            $minor = $this->minorAmount((float) $inquiry->total_amount);
            if ($minor > 0) {
                $data['amount'] = $minor;
                $data['currency'] = 'BDT';
            }
        }

        $event = [
            'id' => $eventId,
            'type' => $isFreeTrial ? 'trial_started' : 'subscription_created',
            'timestamp_ms' => (int) round(microtime(true) * 1000),
            'source_url' => $this->sourceUrl($request),
            'action_source' => 'web',
            'data' => $data,
        ];

        $oppref = $this->opaqueCookie($request, '__oppref', 512);
        if ($oppref !== null) {
            $event['oppref'] = $oppref;
        }

        $user = $this->userMatch($request, $inquiry);
        if ($user !== []) {
            $event['user'] = $user;
        }

        return $event;
    }

    public function minorAmount(float $taka): int
    {
        return max(0, (int) round($taka * 100));
    }

    /**
     * @return array<string, mixed>
     */
    private function userMatch(Request $request, SubscriptionInquiry $inquiry): array
    {
        $user = [];

        $email = $this->hashNormalized($this->normalizeEmail($inquiry->email));
        if ($email !== null) {
            $user['emails_sha256'] = [$email];
        }

        $phones = [];
        foreach ([$inquiry->contact_number ?? null, $inquiry->whatsapp_number ?? null] as $phone) {
            $hash = $this->hashNormalized($this->normalizePhone(is_string($phone) ? $phone : null));
            if ($hash !== null && ! in_array($hash, $phones, true)) {
                $phones[] = $hash;
            }
        }
        if ($phones !== []) {
            $user['phone_numbers_sha256'] = array_slice($phones, 0, 3);
        }

        $external = $this->hashNormalized(trim((string) $inquiry->id));
        if ($external !== null) {
            $user['external_ids_sha256'] = [$external];
        }

        [$first, $last] = $this->splitName((string) ($inquiry->customer_name ?? ''));
        $firstHash = $this->hashNormalized($first);
        $lastHash = $this->hashNormalized($last);
        if ($firstHash !== null) {
            $user['first_names_sha256'] = [$firstHash];
        }
        if ($lastHash !== null) {
            $user['last_names_sha256'] = [$lastHash];
        }

        $user['countries'] = ['BD'];

        $obref = $this->opaqueCookie($request, '__obref', 128);
        if ($obref !== null) {
            $user['obref'] = $obref;
        }

        $ip = $request->ip();
        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            $user['ip_address'] = $ip;
        }

        $agent = trim((string) $request->userAgent());
        if ($agent !== '') {
            $user['user_agent'] = mb_substr($agent, 0, 512);
        }

        return $user;
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    /**
     * Keep the country calling code. Bangladesh local 01XXXXXXXXX becomes 8801XXXXXXXXX.
     */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $digits = '880'.substr($digits, 1);
        }
        $digits = ltrim($digits, '0');
        $length = strlen($digits);

        return $length >= 8 && $length <= 15 ? $digits : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/u', $name, 2) ?: [];
        $first = $this->normalizeName($parts[0] ?? '');
        $last = $this->normalizeName($parts[1] ?? '');

        return [$first, $last];
    }

    private function normalizeName(string $name): ?string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[\s!-\/:-@\[-`{-~]+/u', '', $name) ?? '';

        return $name !== '' ? $name : null;
    }

    private function hashNormalized(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    private function sourceUrl(Request $request): string
    {
        $referer = trim((string) $request->headers->get('referer'));
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $refererHost = parse_url($referer, PHP_URL_HOST);

        if (
            $referer !== ''
            && is_string($refererHost)
            && is_string($appHost)
            && strcasecmp($refererHost, $appHost) === 0
            && str_starts_with($referer, 'http')
        ) {
            return strtok($referer, '#') ?: $referer;
        }

        return rtrim((string) config('app.url'), '/').'/pricing';
    }

    private function opaqueCookie(Request $request, string $name, int $max): ?string
    {
        $value = trim((string) $request->cookie($name));
        if ($value === '' || strlen($value) > $max || preg_match('/[\r\n]/', $value)) {
            return null;
        }

        return $value;
    }

    private function logFailure(Response $response, string $type, string $id): void
    {
        Log::warning('OpenAI Ads conversion was rejected.', [
            'event' => $type,
            'event_id' => $id,
            'status' => $response->status(),
        ]);
    }
}
