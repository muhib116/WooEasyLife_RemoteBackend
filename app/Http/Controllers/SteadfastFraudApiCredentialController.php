<?php

namespace App\Http\Controllers;

use App\Services\FraudCheck\SteadfastFraudApiCredentialStore;
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
