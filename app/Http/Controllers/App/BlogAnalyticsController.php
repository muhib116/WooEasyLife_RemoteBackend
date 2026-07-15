<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\BlogAi\BlogAnalyticsTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogAnalyticsController extends Controller
{
    public function store(Request $request, BlogAnalyticsTracker $tracker): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'event' => ['required', 'string', 'in:view,cta_click,scroll_depth'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'scroll_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
            'visitor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $ok = $tracker->track(
            $request,
            $validated['slug'],
            $validated['event'],
            [
                'cta_label' => $validated['cta_label'] ?? null,
                'scroll_pct' => $validated['scroll_pct'] ?? null,
                'visitor_id' => $validated['visitor_id'] ?? null,
            ],
        );

        return response()->json([
            'ok' => $ok,
            'event' => $validated['event'],
        ]);
    }
}
