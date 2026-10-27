<?php

namespace App\Http\Controllers;

use App\Services\FraudCheck\SteadfastFraudApiCredentialStore;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SteadfastFraudApiCredentialController extends Controller
{
    public function __construct(
        private SteadfastFraudApiCredentialStore $credentials,
    ) {}

    public function index(): Response
    {
        return Inertia::render('FraudCheck/SteadfastApi', [
            'credential' => $this->credentials->adminState(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'api_key' => ['required', 'string', 'max:255'],
            'secret_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->credentials->add($data['api_key'], $data['secret_key'], $data['label'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'api_key' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Steadfast API key added.');
    }

    public function updateAccount(Request $request, string $account): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:180'],
            'api_key' => ['required', 'string', 'max:255'],
            'secret_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->credentials->updateAccount(
                $account,
                $data['api_key'],
                $data['secret_key'],
                $data['label'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'api_key' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Steadfast API key saved.');
    }

    public function importMerchants(): RedirectResponse
    {
        $result = $this->credentials->importMerchantKeys();

        $parts = [
            $result['added'].' merchant '.($result['added'] === 1 ? 'key' : 'keys').' added.',
        ];

        if (($result['updated'] ?? 0) > 0) {
            $parts[] = $result['updated'].' '.($result['updated'] === 1 ? 'key' : 'keys').' updated.';
        }

        if ($result['already_saved'] > 0) {
            $parts[] = $result['already_saved'].' already in the list.';
        }

        if ($result['missing_keys'] > 0) {
            $parts[] = $result['missing_keys'].' '.($result['missing_keys'] === 1 ? 'merchant has' : 'merchants have').' no Steadfast API pair.';
        }

        return back()->with('success', implode(' ', $parts));
    }

    public function test(string $account, SteadfastFraudChecker $checker): RedirectResponse
    {
        $saved = $this->credentials->find($account);

        if ($saved === null) {
            return back()->with('success', 'That Steadfast API key is no longer in the list.');
        }

        $result = $checker->probeApiKey($saved['api_key'], $saved['secret_key']);
        $this->credentials->rememberProbe($saved['api_key'], $result);

        if ($result['ok']) {
            return back()->with('success', 'This key works again. Hub checks can use it.');
        }

        if (! empty($result['temporary'])) {

            $message = ! empty($result['rate_limited'])
                ? 'Steadfast rate limit on this key. It rests for a few minutes, then checks use it again.'
                : 'Steadfast did not answer for this key. It rests for a few minutes, then checks use it again.';

            return back()->with('success', $message);
        }

        $message = $result['error'] ?: 'Steadfast rejected this API key or secret.';

        return back()->with(
            'success',
            'Test failed. '.$message.' Checks ask this key again by themselves every '
                .SteadfastFraudApiCredentialStore::RECHECK_AFTER_HOURS.' hours, and it comes back as soon as Steadfast accepts it.'
        );
    }

    public function destroy(string $account): RedirectResponse
    {
        $this->credentials->remove($account);

        return back()->with('success', 'Steadfast API key removed.');
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->boolean('clear')) {
            $this->credentials->clear();

            return back()->with('success', 'Saved Steadfast API keys removed. .env is used when it has a key pair.');
        }

        $data = $request->validate([
            'api_key' => ['required', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->credentials->put($data['api_key'], $data['secret_key'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'secret_key' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Steadfast API keys saved.');
    }
}
