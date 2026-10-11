<?php

namespace App\Http\Controllers;

use App\Services\FraudCheck\FraudCheckQuotaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FraudCheckQuotaController extends Controller
{
    public function __construct(
        private FraudCheckQuotaSettings $quotas,
    ) {}

    public function index(): Response
    {
        return Inertia::render('FraudCheck/Limits', [
            'quotas' => $this->quotas->snapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'landing_daily_free_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'plugin_daily_free_limit' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $this->quotas->update($data);

        return back()->with('success', 'Daily fraud-check limits saved.');
    }

    public function reset(): RedirectResponse
    {
        $this->quotas->resetToEnv();

        return back()->with('success', 'Daily limits are back to the .env defaults.');
    }
}
