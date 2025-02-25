<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SessionController extends Controller
{
    public function sessions()
    {
        return Inertia::render('Sessions/Index');
    }

    private function decodeSessionPayload($payload)
    {
        $sessionData = null;
        try {
            $payload = base64_decode($payload);
            $sessionData = unserialize($payload);
        } catch (\Throwable $th) {
        }
        return $sessionData;
    }

    public function getSessions()
    {
        $sessions = DB::table('sessions')->get();
        $sessions = $sessions->map(function ($item) {
            $item->decoded_payload = $this->decodeSessionPayload($item->payload);
            return $item;
        });
        return $sessions;
        return response()->json($sessions);
    }

    public function clearSession()
    {
        DB::table('sessions')
            ->where('last_activity', '<', now()->subMinutes(config('session.lifetime'))->timestamp)
            ->delete();
        return response()->json(['success' => true]);
    }
    public function clearAllSession()
    {
        DB::table('sessions')
            ->delete();
        return response()->json(['success' => true]);
    }
}
