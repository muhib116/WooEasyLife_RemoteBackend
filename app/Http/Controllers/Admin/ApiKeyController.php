<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenManage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\AccessToken;

class ApiKeyController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tokens = $user->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'expires_at' => $token->expires_at,
                'domain' => $token->domain,
                'description' => $token->description,
                'bearer_token' => decodeToken($token->access_key),
                'title' => $token->title,
                'abilities' => $token->abilities,
                'name' => $token->name,
                'last_used_ago' => optional($token->last_used_at)->diffForHumans()
            ];
        });

        return Inertia::render('ApiKey/Index', compact('tokens'));
    }

    public function create(Request $request)
    {

        $request->validate([
            'domain' => [
                'required',
                'regex:/^(https?:\/\/)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}(\/.*)?$/'
            ],
        ]);
        // dd($request->all());

        $user = User::find(Auth::id());
        $title = $user->name . '(' . $user->id . ')';
        $token = $user->createToken($title, ['*']);
        $plainTextToken = $token->plainTextToken;
        $accessToken = $token->accessToken;

        $accessToken = AccessToken::find($accessToken->id);
        $accessToken->update([
            'access_key' => encodeToken($plainTextToken),
            'title' => $title,
            'domain' => $request->domain ?? null,
            'expires_at' => $request->expires_at ?? null
        ]);
        return back()->with('success', 'Access token generated successfully!');
    }
    public function update(Request $request, $id)
    {

        $request->validate([
            'domain' => [
                'required',
                'regex:/^(https?:\/\/)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}(\/.*)?$/'
            ],
        ]);

        $accessToken = AccessToken::findOrFail($id);
        // dd($accessToken);
        $accessToken->update([
            'description' => $request->description ?? null,
            'domain' => $request->domain ?? null,
            'expires_at' => $request->expires_at ?? null
        ]);

        return back()->with('success', 'Access token info updated successfully!');
    }

    public function delete(Request $request)
    {
        $request->user()->tokens()->where('id', $request->tokenId)->first()->delete();
        return back(303);
    }

    private function generateUniqueApiKey()
    {
        // $key = generateUniqueApiKey();
        // do {
        //     $apiKey = generateUniqueApiKey();
        // } while (AccessToken::where('key', $apiKey)->exists()); // Check for uniqueness

        // return $apiKey;
    }
}
