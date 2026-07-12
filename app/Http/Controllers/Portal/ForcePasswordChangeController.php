<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! ($request->user()?->must_change_password ?? false)) {
            return redirect()->route('portal.dashboard');
        }

        return Inertia::render('Portal/ForcePasswordChange');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! ($user?->must_change_password ?? false)) {
            return redirect()->route('portal.dashboard');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()
            ->route('portal.dashboard')
            ->with('success', 'Password updated. You can continue using the portal.');
    }
}
