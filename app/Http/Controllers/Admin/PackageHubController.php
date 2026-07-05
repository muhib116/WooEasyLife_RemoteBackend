<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageHub;
use App\Models\UserPackage;
use App\Services\PackagePlanResolver;
use App\Support\PackageCatalogFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PackageHubController extends Controller
{
    public function __construct(
        protected PackagePlanResolver $planResolver
    ) {
    }

    public function index()
    {
        $packages = PackageHub::withTrashed()
            ->with('creator')
            ->withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Package/Index', compact('packages'));
    }

    public function create(Request $request)
    {
        $validated = $this->validateCatalogPayload($request);
        $features = PackageCatalogFeatures::normalize($validated['features'] ?? []);

        PackageHub::create([
            'title' => $validated['package_name'],
            'description' => $validated['description'] ?? null,
            'per_order_rate' => 0,
            'package_duration' => $validated['package_duration'],
            'trial_days' => $validated['package_duration'] === 'free_trial'
                ? $validated['trial_days']
                : null,
            'order_rate_token' => $validated['order_rate_token'],
            'package_price' => $validated['package_price'],
            'app_connect' => $request->boolean('app_connect'),
            'total_website_connect' => $request->boolean('app_connect')
                ? ($validated['total_website_connect'] ?? null)
                : null,
            'features' => $features,
            'is_active' => $request->boolean('is_active'),
            'is_special' => $request->boolean('is_special'),
            'created_by' => Auth::id(),
            'index' => PackageHub::withTrashed()->count() + 1,
        ]);

        return back()->with('success', 'Package created successfully!');
    }

    public function update(Request $request, int $id)
    {
        $package = PackageHub::query()->findOrFail($id);

        if (! $this->planResolver->isCatalog($package)) {
            return back()->with('error', 'Legacy packages cannot be edited from the catalog form.');
        }

        $validated = $this->validateCatalogPayload($request);
        $features = PackageCatalogFeatures::normalize($validated['features'] ?? []);

        $package->update([
            'title' => $validated['package_name'],
            'description' => $validated['description'] ?? null,
            'package_duration' => $validated['package_duration'],
            'trial_days' => $validated['package_duration'] === 'free_trial'
                ? $validated['trial_days']
                : null,
            'order_rate_token' => $validated['order_rate_token'],
            'package_price' => $validated['package_price'],
            'app_connect' => $request->boolean('app_connect'),
            'total_website_connect' => $request->boolean('app_connect')
                ? ($validated['total_website_connect'] ?? null)
                : null,
            'features' => $features,
            'is_active' => $request->boolean('is_active'),
            'is_special' => $request->boolean('is_special'),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Package updated successfully!');
    }

    public function destroy(int $id)
    {
        $package = PackageHub::query()->findOrFail($id);

        if ($package->trashed()) {
            return back()->with('error', 'This package has already been deleted.');
        }

        if (UserPackage::query()->where('package_hub_id', $package->id)->exists()) {
            return back()->with(
                'error',
                'Cannot delete this package because it is assigned to one or more merchants.',
            );
        }

        $package->update(['updated_by' => Auth::id()]);
        $package->delete();

        return back()->with('success', 'Package deleted successfully!');
    }

    public function restore(int $id)
    {
        $package = PackageHub::withTrashed()->findOrFail($id);

        if (! $package->trashed()) {
            return back()->with('error', 'This package is not deleted.');
        }

        $package->update(['updated_by' => Auth::id()]);
        $package->restore();

        return back()->with('success', 'Package restored successfully!');
    }

    public function toggleStatus(int $id)
    {
        $package = PackageHub::withTrashed()->findOrFail($id);

        if ($package->trashed()) {
            return back()->with(
                'error',
                'Deleted packages must be restored before changing status.',
            );
        }

        $package->update([
            'is_active' => ! $package->is_active,
            'updated_by' => Auth::id(),
        ]);

        $label = $package->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "Package {$label} successfully!");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCatalogPayload(Request $request): array
    {
        return $request->validate([
            'package_name' => ['required', 'string', 'max:255'],
            'package_duration' => [
                'required',
                'string',
                Rule::in(['free_trial', '1_month', '5_month', '1_year']),
            ],
            'trial_days' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($request->input('package_duration') === 'free_trial'),
            ],
            'order_rate_token' => ['required', 'integer', 'min:0'],
            'package_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_special' => ['sometimes', 'boolean'],
            'app_connect' => ['sometimes', 'boolean'],
            'total_website_connect' => ['nullable', 'integer', 'min:1', 'max:5'],
            'features' => ['required', 'array'],
        ]);
    }
}
