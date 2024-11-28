<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function index()
    {
        return Inertia::render('ApiKey/Index');
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        return $user->tokens->map(function ($token) {
            return [
                'token' => $token->token,
                'abilities' => $token->abilities,
                'name' => $token->name,
            ];
        });
        // dd($user->createToken('Token Name')->accessToken);
    }
}
