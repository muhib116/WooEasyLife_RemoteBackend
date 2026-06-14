<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhitelistedDomain;
use App\Services\WhitelistedDomainService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhitelistedDomainController extends Controller
{
    public function __construct(
        private WhitelistedDomainService $whitelistedDomainService,
    ) {}

    public function index()
    {
        return Inertia::render('WhitelistedDomains/Index', [
            'domains' => WhitelistedDomain::query()
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $domain = $this->whitelistedDomainService->normalizeDomain($validated['domain']);

        if (!$domain) {
            return back()->withErrors(['domain' => 'Enter a valid domain like example.com']);
        }

        WhitelistedDomain::updateOrCreate(
            ['domain' => $domain],
            [
                'notes' => $validated['notes'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        $this->whitelistedDomainService->forgetCache();

        return back()->with('success', 'Domain whitelisted successfully.');
    }

    public function update(Request $request, WhitelistedDomain $whitelistedDomain)
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $domain = $this->whitelistedDomainService->normalizeDomain($validated['domain']);

        if (!$domain) {
            return back()->withErrors(['domain' => 'Enter a valid domain like example.com']);
        }

        if (
            $domain !== $whitelistedDomain->domain
            && WhitelistedDomain::where('domain', $domain)->where('id', '!=', $whitelistedDomain->id)->exists()
        ) {
            return back()->withErrors(['domain' => 'This domain is already whitelisted.']);
        }

        $whitelistedDomain->update([
            'domain' => $domain,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        $this->whitelistedDomainService->forgetCache();

        return back()->with('success', 'Whitelist domain updated.');
    }

    public function destroy(WhitelistedDomain $whitelistedDomain)
    {
        $whitelistedDomain->delete();
        $this->whitelistedDomainService->forgetCache();

        return back()->with('success', 'Domain removed from whitelist.');
    }
}
