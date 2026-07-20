<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSidebarNavOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarNavOrderController extends Controller
{
    public function __construct(
        private AdminSidebarNavOrder $navOrder,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'order' => $this->navOrder->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sections' => ['nullable', 'array', 'max:30'],
            'sections.*' => ['string', 'max:120'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*' => ['array', 'max:50'],
            'items.*.*' => ['string', 'max:120'],
            'children' => ['nullable', 'array', 'max:30'],
            'children.*' => ['array', 'max:50'],
            'children.*.*' => ['string', 'max:120'],
        ]);

        $order = $this->navOrder->update([
            'sections' => $validated['sections'] ?? [],
            'items' => $validated['items'] ?? [],
            'children' => $validated['children'] ?? [],
        ]);

        return response()->json([
            'order' => $order,
            'message' => 'Sidebar menu order saved.',
        ]);
    }
}
