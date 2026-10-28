<?php

namespace App\Http\Controllers;

use App\Services\FraudCheck\BulkFraudRuntimeSettings;
use App\Services\FraudCheck\BulkFraudScheduler;
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
        private BulkFraudScheduler $scheduler,
    ) {}

    public function index(): Response
    {
        return Inertia::render('FraudCheck/Limits', [
            'quotas' => $this->quotas->snapshot(),
            'bulk' => $this->bulk->snapshot(),
            'scheduler' => $this->scheduler->status(),
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

        return back()->with('success', 'Daily limits are back to default.');
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

        return back()->with('success', 'Bulk check settings are back to default.');
    }

    public function updateScheduler(Request $request): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required'],
        ]);
        $enabled = $request->boolean('enabled');

        $this->scheduler->setEnabled($enabled);

        return back()->with('success', $enabled
            ? 'Background checking is on. The server runs it every minute.'
            : 'Background checking is off. Open batches still check while their page is open.');
    }

    public function runScheduler(): RedirectResponse
    {
        $this->scheduler->start();

        return back()->with('success', 'Background checking started. It keeps going for about a minute.');
    }
}
