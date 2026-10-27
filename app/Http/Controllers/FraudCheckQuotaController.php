<?php

namespace App\Http\Controllers;

use App\Services\FraudCheck\BulkFraudRuntimeSettings;
use App\Services\FraudCheck\FraudCheckQuotaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FraudCheckQuotaController extends Controller
{
    public function __construct(
        private FraudCheckQuotaSettings $quotas,
        private BulkFraudRuntimeSettings $bulk,
    ) {}

    public function index(): Response
    {
        return Inertia::render('FraudCheck/Limits', [
            'quotas' => $this->quotas->snapshot(),
            'bulk' => $this->bulk->snapshot(),
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

    public function updateBulk(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (BulkFraudRuntimeSettings::FIELDS as $field => $meta) {
            $rules[$field] = ['required', 'integer', 'min:'.$meta['min'], 'max:'.$meta['max']];
        }

        $this->bulk->update($request->validate($rules));

        return back()->with('success', 'Bulk check settings saved.');
    }

    public function resetBulk(): RedirectResponse
    {
        $this->bulk->resetToEnv();

        return back()->with('success', 'Bulk check settings are back to the .env defaults.');
    }
}
