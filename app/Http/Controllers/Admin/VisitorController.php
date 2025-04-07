<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RouteHit;
use Carbon\Carbon;
use Inertia\Inertia;

class VisitorController extends Controller
{

    public function index()
    {
        return Inertia::render('Visitor/Index');
    }

    public function getRouteHitReport(Request $request)
    {
        $query = RouteHit::query();

        // Optional filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('path')) {
            $query->where('path', 'like', '%' . $request->input('path') . '%');
        }

        if ($request->filled('domain')) {
            $query->where('domain', 'like', '%' . $request->input('domain') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->input('date_from'))->startOfDay(),
                Carbon::parse($request->input('date_to'))->endOfDay()
            ]);
        }

        // Handle different report types
        switch ($request->input('type')) {
            case 'daily':
                $data = $query->selectRaw('DATE(created_at) as date, SUM(hit_count) as total_hits')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
                break;

            case 'by_path':
                $data = $query->select('path', DB::raw('SUM(hit_count) as total_hits'))
                    ->groupBy('path')
                    ->orderByDesc('total_hits')
                    ->get();
                break;

            case 'by_domain':
                $data = $query->select('domain', DB::raw('SUM(hit_count) as total_hits'))
                    ->groupBy('domain')
                    ->orderByDesc('total_hits')
                    ->get();
                break;

            case 'errors':
                $data = $query->whereNotNull('error')
                    ->select('path', 'domain', 'status', 'error', 'created_at')
                    ->orderByDesc('created_at')
                    ->get();
                break;

            case 'full':
            default:
                $data = $query->orderByDesc('created_at')->get();
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
