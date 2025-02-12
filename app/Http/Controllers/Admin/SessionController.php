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

    public function getSessions()
    {
        $sessions = DB::table('sessions')->get();
        return response()->json($sessions);
    }

    public function clearSession()
    {
        DB::table('sessions')
            ->where('last_activity', '<', now()->subMinutes(config('session.lifetime'))->timestamp)
            ->delete();
        return response()->json(['success' => true]);
    }
}
