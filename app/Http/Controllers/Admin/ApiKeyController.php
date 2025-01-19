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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    private function decodeToken($encryptedToken)
    {
        try {
            return Crypt::decryptString($encryptedToken);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Handle decryption error (e.g., log the error, throw an exception, etc.)
            return null;
        }
    }

    private function encodeToken($token)
    {
        return Crypt::encryptString($token);
    }

    public function index()
    {
        $users = User::where('role', 'user')->get();

        $users = $users->map(function ($user) {
            $tokens = AccessToken::where('tokenable_id', $user->id)->get();
            $user->tokens = $tokens->map(function ($token) {
                return [
                    ...$token->toArray(),
                    // 'id' => $token->id,
                    // 'expires_at' => $token->expires_at,
                    // 'domain' => $token->domain,
                    // 'description' => $token->description,
                    'bearer_token' => $this->decodeToken($token->access_key),
                    // 'title' => $token->title,
                    // 'abilities' => $token->abilities,
                    // 'name' => $token->name,
                    'last_used_ago' => optional($token->last_used_at)->diffForHumans()
                ];
            });
            return $user;
        });
        // return $users;
        // $user = Auth::user();

        return Inertia::render('ApiKey/Index', compact('users'));
    }

    public function create(Request $request)
    {

        // $request->validate([
        //     'domain' => [
        //         'nullable',
        //         'regex:/^(https?:\/\/)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}(\/.*)?$/'
        //     ],
        // ]);

        if (!$request->tokenable_id) {
            return back()->with('error', 'No selected user found');
        }

        $user = User::find($request->tokenable_id);
        if (!$user) {
            return back()->with('error', 'No user found against user id ' . $request->tokenable_id);
        }

        try {
            $tokenLength = AccessToken::where('tokenable_id', $user->id)->count();
            $title = $user->name . '(' . $user->id . ') - t(' . $tokenLength . ')';
            $token = $user->createToken($title, ['*']);
            $plainTextToken = $token->plainTextToken;
            $accessToken = $token->accessToken;
            DB::beginTransaction();
            $accessToken = AccessToken::find($accessToken->id);

            $accessToken->update([
                'access_key' => $this->encodeToken($plainTextToken),
                'title' => $title,
                'domain' => $request->domain ?? null,
                'expires_at' => $request->expires_at ?? null
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }

        return back()->with('success', 'Access token generated successfully!');
    }
    public function update(Request $request, $id)
    {

        // $request->validate([
        //     'domain' => [
        //         'required',
        //         'regex:/^(https?:\/\/)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}(\/.*)?$/'
        //     ],
        // ]);

        $accessToken = AccessToken::findOrFail($id);
        // dd($accessToken);
        $accessToken->update([
            'description' => $request->description ?? null,
            'domain' => $request->domain ?? null,
            'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null
        ]);

        return back()->with('success', 'Access token info updated successfully!');
    }

    public function delete($id)
    {
        $accessToken = AccessToken::findOrFail($id);
        $accessToken->delete();
        return back()->with('success', 'Token deleted successfully');
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
