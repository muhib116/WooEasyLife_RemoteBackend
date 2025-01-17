<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tokens = $user->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'token' => $token->plainTextToken,
                'abilities' => $token->abilities,
                'name' => $token->name,
                'last_used_ago' => optional($token->last_used_at)->diffForHumans()
            ];
        });
        // return $tokens;
        return Inertia::render('ApiKey/Index', compact('tokens'));
    }

    public function create(Request $request)
    {
        // $user = Auth::user();
        $user = User::find(Auth::id());
        $existingToken = $user->tokens()->latest()->first();
        dd($existingToken->plainTextToken);
        // $token = $request->user()->createToken(
        //     $user->name . '(' . $user->id . ')',
        //     ['*']
        // );
        // return $token;
        // return $user->createToken('Token Name')->accessToken;
    }
    public function delete(Request $request)
    {
        $request->user()->tokens()->where('id', $request->tokenId)->first()->delete();
        return back(303);
    }
}
