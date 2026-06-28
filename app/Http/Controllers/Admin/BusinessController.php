<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index($userId)
    {
        return redirect()->route('users.websites', [
            'user_id' => $userId,
        ]);
    }

    public function store(Request $request, $userId)
    {
        return redirect()
            ->route('users.websites', ['user_id' => $userId])
            ->with('error', 'Business profiles are managed from Merchant → Websites.');
    }
}
