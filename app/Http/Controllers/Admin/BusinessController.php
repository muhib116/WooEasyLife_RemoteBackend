<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function index($userId)
    {
        $user = User::find($userId);
        return Inertia::render('Users/Business/Index', compact('user'));
    }
}
