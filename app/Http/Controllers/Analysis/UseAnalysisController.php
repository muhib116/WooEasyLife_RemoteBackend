<?php

namespace App\Http\Controllers\Analysis;

use App\Http\Controllers\Controller;
use App\Models\PackageUseHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UseAnalysisController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'user')->get();
        return Inertia::render('UserAnalysis/Index', compact('users'));
    }

    public function getUseReport(Request $request)
    {
        $query = PackageUseHistory::where('user_id', $request->user_id);

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $history = $query->get();

        $modifiedHistory = collect($history)->map(function ($record) {
            // return $record->use_details;
            $useDetails = collect($record->use_details);
            $record->use_details = $useDetails->map(function ($item) {
                try {
                    if (is_string($item['cart_contents']) && @unserialize($item['cart_contents']) !== false) {
                        $item['cart_contents'] = unserialize($item['cart_contents']);
                    }
                } catch (\Throwable $th) {
                }
                return $item;
            });

            return $record;
        });

        return response()->json($modifiedHistory ?? []);
    }
}
