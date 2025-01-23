<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()->where('role', 'user')->orderBy('id', 'desc')->get();
        return Inertia::render('Users/Index', compact('users'));
    }

    public function view($userId)
    {
        $user = User::find($userId);

        return Inertia::render('Users/View', compact('user'));
    }
    public function apiKeys($userId)
    {
        $user = User::findOrFail($userId);
        $tokens = AccessToken::where('tokenable_id', $user->id)->get();
        $tokens = $tokens->map(function ($token) {
            return [
                ...$token->toArray(),
                'bearer_token' => $this->decodeToken($token->access_key),
                'last_used_ago' => optional($token->last_used_at)->diffForHumans()
            ];
        });

        return Inertia::render('Users/ApiKeys', compact('user', 'tokens'));
    }
    public function packages($userId)
    {
        $user = User::find($userId);
        return Inertia::render('Users/Packages', compact('user'));
    }
}
