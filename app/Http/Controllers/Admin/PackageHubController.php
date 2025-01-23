<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PackageHubController extends Controller
{
    public function index()
    {
        $packages = PackageHub::withTrashed()
            ->with('creator')
            ->orderBy('id', 'desc')
            ->get();
        return Inertia::render('Package/Index', compact('packages'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'per_order_rate' => 'required'
        ]);

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'per_order_rate' => $request->per_order_rate,
            'is_active' => $request->is_active,
            'created_by' => Auth::id(),
            'index' => PackageHub::withTrashed()->count() + 1
        ];
        PackageHub::create($data);
        return back()->with('success', 'Package created successfully!');
    }
}
