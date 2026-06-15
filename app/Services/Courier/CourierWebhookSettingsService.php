<?php

namespace App\Services\Courier;

use App\Models\AccessToken;
use App\Models\CourierAccount;
use App\Models\CourierConfiguration;
use App\Models\CourierHubToken;
use App\Models\CourierShipment;
use App\Models\LicenseCourierAccount;
use Illuminate\Http\Request;

class CourierWebhookSettingsService
{
    public function __construct(
        protected CourierAccountService $accountService
    ) {
    }

    public function buildSettings(string $partner, Request $request, array $context = []): array
    {
        $partner = strtolower(trim($partner));
        $environment = ($request->query('environment') === 'sandbox') ? 'sandbox' : 'live';
        $accessToken = $this->accountService->resolveAccessToken($request);

        $callbackUrl = $this->callbackUrl($partner, $environment);
        $redxToken = $partner === 'redx' ? CourierHubToken::tokenForPartner('redx') : '';

        if ($partner === 'redx' && $redxToken !== '') {
            $callbackUrl .= (str_contains($callbackUrl, '?') ? '&' : '?') . 'token=' . urlencode($redxToken);
        }

        $sharedCount = 0;
        $sharedAccount = false;

        if ($accessToken) {
            $link = LicenseCourierAccount::query()
                ->where('access_token_id', $accessToken->id)
                ->where('is_current', true)
                ->whereHas('courierAccount', function ($query) use ($partner, $environment) {
                    $query->where('partner', $partner)->where('environment', $environment);
                })
                ->with('courierAccount')
                ->first();

            if ($link?->courierAccount) {
                $sharedCount = LicenseCourierAccount::query()
                    ->where('courier_account_id', $link->courier_account_id)
                    ->where('is_current', true)
                    ->count();
                $sharedAccount = $sharedCount > 1;
            }
        }

        $authHints = [
            'steadfast' => 'Use your Steadfast API Key as the Bearer token in Steadfast webhook settings.',
            'pathao' => 'Use your Client Webhook Secret in Woo Easy Life. Pathao signs requests with X-PATHAO-Signature.',
            'redx' => 'Register the full callback URL including the token query parameter shown below.',
        ];

        $setupSteps = [
            'Save courier settings here first (syncs webhook authentication).',
            'If you already have courier orders, run Sync existing orders in Webhook activity.',
            'Copy the Woo Easy Life webhook callback URL below.',
            'Open your courier merchant portal webhook settings.',
            'Paste the callback URL and save.',
            'Create or update a test order to confirm automatic status updates.',
        ];

        if ($partner === 'pathao') {
            $setupSteps[] = 'If you change API credentials later, register the same callback URL in your new Pathao account.';
        }

        $credentialsChanged = (bool) ($context['credentials_changed'] ?? false);
        $inFlight = (int) ($context['in_flight_shipments'] ?? 0);
        $mappingCount = $this->shipmentMappingCount($accessToken?->id, $partner, $environment);
        $hubReady = $mappingCount > 0;

        $actionRequired = $credentialsChanged || $callbackUrl === '' || !$hubReady;
        $actionMessage = null;

        if (!$hubReady) {
            $actionMessage = 'If you already have courier orders on this store, run Sync existing orders before registering this callback URL in your courier portal.';
        } elseif ($credentialsChanged && $inFlight > 0) {
            $actionMessage = 'Register this callback URL in your new courier merchant portal. Keep it on the old account until in-flight orders finish.';
        } elseif ($credentialsChanged) {
            $actionMessage = 'Register this callback URL in your new courier merchant portal.';
        }

        return [
            'mode' => 'hub',
            'callback_url' => $callbackUrl,
            'environment' => $environment,
            'shared_account' => $sharedAccount,
            'shared_with_license_count' => max($sharedCount, 1),
            'webhook_auth' => [
                'type' => $partner === 'pathao' ? 'signature' : ($partner === 'steadfast' ? 'bearer' : 'query_token'),
                'hint' => $authHints[$partner] ?? '',
            ],
            'setup_steps' => $setupSteps,
            'action_required' => $actionRequired,
            'action_message' => $actionMessage,
            'credentials_changed' => $credentialsChanged,
            'in_flight_shipments' => $inFlight,
            'redx_webhook_token' => $redxToken,
            'redx_token_appended' => $partner === 'redx',
            'hub_shipment_mappings' => $mappingCount,
            'hub_ready' => $hubReady,
        ];
    }

    public function shipmentMappingCount(?int $accessTokenId, string $partner, string $environment = 'live'): int
    {
        if (!$accessTokenId) {
            return 0;
        }

        return (int) CourierShipment::query()
            ->where('access_token_id', $accessTokenId)
            ->where('partner', strtolower(trim($partner)))
            ->where('environment', $environment === 'sandbox' ? 'sandbox' : 'live')
            ->count();
    }

    public function callbackUrl(string $partner, string $environment = 'live'): string
    {
        $partner = strtolower(trim($partner));
        $path = '/api/webhooks/' . $partner;

        if ($environment === 'sandbox') {
            $path .= '/sandbox';
        }

        return rtrim((string) config('app.url'), '/') . $path;
    }
}
