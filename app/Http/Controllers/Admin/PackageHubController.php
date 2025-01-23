<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageHubController extends Controller
{
    public function index()
    {
        $packages = [];
        return Inertia::render('Package/Index', compact('packages'));
    }
}
