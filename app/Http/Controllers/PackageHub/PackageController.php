<?php

namespace App\Http\Controllers\PackageHub;

use App\Http\Controllers\Controller;
use App\Models\PackageHub;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function getList()
    {
        $packages = PackageHub::query()
            ->where('is_active', true)
            ->orderBy('index', 'desc')
            ->get();
        return $this->successResponse($packages ?? []);
    }

    public function purchasePackage(Request $request)
    {
        // desc
    }
}
