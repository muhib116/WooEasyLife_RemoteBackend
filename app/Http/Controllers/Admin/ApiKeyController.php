<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AccessToken;
use App\Services\DomainNormalizer;
use App\Services\LicenseProvisioningService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'user')->get();

        $users = $users->map(function ($user) {
            $tokens = AccessToken::where('tokenable_id', $user->id)->get();
            $user->tokens = $tokens->map(function ($token) {
                return [
                    ...$token->toArray(),
                    'has_token' => ! empty($token->access_key),
                    'last_used_ago' => optional($token->last_used_at)->diffForHumans(),
                ];
            });

            return $user;
        });

        return Inertia::render('ApiKey/Index', compact('users'));
    }

    public function reveal($id)
    {
        $accessToken = AccessToken::findOrFail($id);

        if (empty($accessToken->access_key)) {
            return response()->json([
                'message' => 'No stored license key for this token.',
            ], 404);
        }

        $plainTextToken = $this->decodeToken($accessToken->access_key);

        if (! $plainTextToken) {
            return response()->json([
                'message' => 'Unable to decrypt the license key.',
            ], 422);
        }

        return response()->json([
            'token' => $plainTextToken,
        ]);
    }

    public function create(Request $request, LicenseProvisioningService $licenseProvisioning)
    {
        $request->validate([
            'domain' => 'required',
            'tokenable_id' => 'required|integer',
            'user_package_id' => 'required|integer',
        ]);

        $user = User::find($request->tokenable_id);
        if (! $user) {
            return back()->with('error', 'No user found against user id ' . $request->tokenable_id);
        }

        try {
            $licenseProvisioning->create(
                $user,
                $request->domain,
                [
                    'title' => $request->title,
                    'description' => $request->description,
                    'status' => $request->status,
                    'expires_at' => $request->expires_at,
                    'user_package_id' => $request->user_package_id,
                ],
                requireUserPackage: true,
                requireDns: true
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }

        return back()->with('success', 'Access token generated successfully!');
    }

    public function update(Request $request, $id, LicenseProvisioningService $licenseProvisioning)
    {
        $accessToken = AccessToken::findOrFail($id);

        try {
            $licenseProvisioning->update($accessToken, [
                'title' => $request->title,
                'description' => $request->description,
                'domain' => $request->domain,
                'status' => $request->status,
                'expires_at' => $request->expires_at,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Access token info updated successfully!');
    }

    public function delete($id)
    {
        $accessToken = AccessToken::findOrFail($id);
        $accessToken->delete();

        return back()->with('success', 'Token deleted successfully');
    }
}
