<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WiseAiAdminController extends Controller
{
    public function dashboard(): Response
    {
        $today = now()->startOfDay();
        $todayTurns = WiseTurn::where('created_at', '>=', $today);

        return Inertia::render('WiseAi/Dashboard', [
            'stats' => [
                'turns_today' => (clone $todayTurns)->count(),
                'turns_total' => WiseTurn::count(),
                'avg_confidence' => round((float) WiseTurn::where('created_at', '>=', $today)
                    ->get(['decision'])
                    ->avg(fn (WiseTurn $turn) => (int) ($turn->decision['confidence'] ?? 0))),
                'active_keys' => WiseApiKey::where('status', 'active')->count(),
                'gaps_today' => WiseTurn::where('created_at', '>=', $today)->where('gap', true)->count(),
                'needs_human_today' => WiseTurn::where('created_at', '>=', $today)
                    ->get(['decision'])
                    ->filter(fn (WiseTurn $turn) => ($turn->decision['action'] ?? '') === 'needs_human')
                    ->count(),
                'published_knowledge' => WiseKnowledgeItem::where('status', 'published')->count(),
            ],
            'recentTurns' => WiseTurn::with('apiKey:id,name')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (WiseTurn $turn) => [
                    'id' => $turn->id,
                    'key_name' => $turn->apiKey?->name,
                    'channel' => $turn->channel,
                    'text' => $turn->text,
                    'intent' => $turn->decision['intent'] ?? null,
                    'confidence' => $turn->decision['confidence'] ?? null,
                    'action' => $turn->decision['action'] ?? null,
                    'source' => $turn->decision['source'] ?? null,
                    'gap' => (bool) $turn->gap,
                    'latency_ms' => $turn->latency_ms,
                    'created_at' => $turn->created_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function playground(): Response
    {
        return Inertia::render('WiseAi/Playground');
    }

    public function config(): Response
    {
        return Inertia::render('WiseAi/Config', [
            'apiKeys' => WiseApiKey::latest()
                ->get()
                ->map(fn (WiseApiKey $key) => $this->keyRow($key)),
        ]);
    }

    public function knowledge(): Response
    {
        return Inertia::render('WiseAi/Knowledge', [
            'apiKeys' => WiseApiKey::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'key_prefix']),
            'items' => WiseKnowledgeItem::with('apiKey:id,name')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (WiseKnowledgeItem $item) => $this->knowledgeRow($item)),
        ]);
    }

    public function storeKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $result = WiseApiKey::generate($validated['name']);

        return response()->json([
            'ok' => true,
            'plain_key' => $result['plain'],
            'key' => $this->keyRow($result['key']),
        ]);
    }

    public function revokeKey(WiseApiKey $key): JsonResponse
    {
        $key->update(['status' => 'revoked']);

        return response()->json(['ok' => true]);
    }

    public function storeKnowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wise_api_key_id' => 'required|integer|exists:wise_api_keys,id',
            'type' => 'required|in:faq,product,policy,other',
            'title' => 'required|string|max:191',
            'question' => 'nullable|string|max:2000',
            'answer' => 'required|string|max:5000',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:60',
        ]);

        // Always draft — only publishKnowledge() may set published (human approval).
        $item = WiseKnowledgeItem::create([
            'wise_api_key_id' => $validated['wise_api_key_id'],
            'type' => $validated['type'],
            'title' => $validated['title'],
            'question' => $validated['question'] ?? null,
            'answer' => $validated['answer'],
            'keywords' => $validated['keywords'] ?? [],
            'status' => 'draft',
            'version' => 1,
        ]);

        $item->load('apiKey:id,name');

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function updateKnowledge(Request $request, WiseKnowledgeItem $item): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:faq,product,policy,other',
            'title' => 'sometimes|string|max:191',
            'question' => 'nullable|string|max:2000',
            'answer' => 'sometimes|string|max:5000',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:60',
        ]);

        if (isset($validated['answer']) || isset($validated['title']) || isset($validated['question'])) {
            $validated['version'] = $item->version + 1;
            // Content change unpublishes until human re-approves.
            $validated['status'] = 'draft';
        }

        $item->update($validated);
        $item->load('apiKey:id,name');

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function publishKnowledge(WiseKnowledgeItem $item): JsonResponse
    {
        $item->update(['status' => 'published']);
        $item->load('apiKey:id,name');

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function unpublishKnowledge(WiseKnowledgeItem $item): JsonResponse
    {
        $item->update(['status' => 'draft']);
        $item->load('apiKey:id,name');

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    private function keyRow(WiseApiKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'status' => $key->status,
            'turns_count' => $key->turns_count,
            'last_used_at' => $key->last_used_at?->toDateTimeString(),
            'created_at' => $key->created_at?->toDateTimeString(),
        ];
    }

    private function knowledgeRow(WiseKnowledgeItem $item): array
    {
        return [
            'id' => $item->id,
            'wise_api_key_id' => $item->wise_api_key_id,
            'key_name' => $item->apiKey?->name,
            'type' => $item->type,
            'title' => $item->title,
            'question' => $item->question,
            'answer' => $item->answer,
            'keywords' => $item->keywords ?? [],
            'status' => $item->status,
            'version' => $item->version,
            'updated_at' => $item->updated_at?->toDateTimeString(),
        ];
    }
}
