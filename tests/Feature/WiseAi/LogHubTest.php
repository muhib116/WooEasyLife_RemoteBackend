<?php

use App\Models\User;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use Illuminate\Support\Facades\Hash;

function wiseLogAdmin(): User
{
    return User::create([
        'name' => 'Wise Log Admin',
        'email' => 'wise-log-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

function wiseLogTurn(WiseApiKey $key, array $overrides = []): WiseTurn
{
    return WiseTurn::create(array_merge([
        'wise_api_key_id' => $key->id,
        'channel' => 'messenger',
        'conversation_id' => 'log-conv-1',
        'text' => 'delivery charge koto?',
        'payload' => [
            'text' => 'delivery charge koto?',
            'channel' => 'messenger',
            'conversation_id' => 'log-conv-1',
            'context' => ['thread' => ['last_intent' => 'shipping']],
        ],
        'decision' => [
            'action' => 'suggest_reply',
            'intent' => 'shipping_charge',
            'confidence' => 88,
            'source' => 'knowledge',
            'suggested_reply' => 'ঢাকায় ৬০ টাকা।',
            'brain_version' => 'test',
        ],
        'evidence' => ['knowledge_id' => 1, 'match_score' => 0.91],
        'trace' => ['P1_normalize' => ['ok' => true]],
        'status' => 'ok',
        'gap' => false,
        'latency_ms' => 42,
    ], $overrides));
}

it('renders wise ai log hub with stats and turn detail json', function () {
    $admin = wiseLogAdmin();
    $key = WiseApiKey::generate('log-hub-test')['key'];
    $turn = wiseLogTurn($key, [
        'evidence' => ['knowledge_id' => 1, 'match_score' => 0.91],
        'trace' => ['P1_normalize' => ['ok' => true], 'P4_knowledge' => ['hit' => true, 'score' => 0.91]],
    ]);

    try {
        $this->actingAs($admin)
            ->get(route('wiseAi.log', [
                'channel' => 'messenger',
                'q' => 'delivery',
                'hours' => 0,
                'turn' => $turn->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WiseAi/Log')
                ->has('turns')
                ->has('stats')
                ->has('pagination')
                ->where('filters.channel', 'messenger')
                ->where('filters.q', 'delivery')
                ->where('filters.hours', 0)
                ->where('filters.turn', $turn->id)
                ->where('stats.matched', 1));

        $this->actingAs($admin)
            ->getJson(route('wiseAi.log.turn', ['turn' => $turn->id]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.text', 'delivery charge koto?')
            ->assertJsonPath('request.context.thread.last_intent', 'shipping')
            ->assertJsonPath('response.decision.action', 'suggest_reply')
            ->assertJsonPath('response.decision.suggested_reply', 'ঢাকায় ৬০ টাকা।')
            ->assertJsonPath('highlights.knowledge_id', 1)
            ->assertJsonPath('trace.P1_normalize.ok', true)
            ->assertJsonPath('trace_steps.0.key', 'P1_normalize')
            ->assertJsonPath('thread.0.id', $turn->id);
    } finally {
        $turn->delete();
        $key->delete();
    }
});

it('reports honest p95 latency across the filtered set not the fastest sample', function () {
    $admin = wiseLogAdmin();
    $key = WiseApiKey::generate('log-p95')['key'];
    $suffix = uniqid('p95-');
    $turns = [];

    // 20 turns: latency 10,20,...,200. p95 index = floor(19*0.95)=18 → 190ms.
    try {
        for ($i = 1; $i <= 20; $i++) {
            $turns[] = wiseLogTurn($key, [
                'channel' => 'p95_channel',
                'conversation_id' => $suffix,
                'text' => 'p95 probe '.$i,
                'latency_ms' => $i * 10,
                'decision' => [
                    'action' => 'suggest_reply',
                    'intent' => 'test',
                    'confidence' => 50,
                    'source' => 'pattern',
                    'suggested_reply' => 'ok',
                ],
            ]);
        }

        $this->actingAs($admin)
            ->get(route('wiseAi.log', [
                'channel' => 'p95_channel',
                'conversation_id' => $suffix,
                'hours' => 0,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WiseAi/Log')
                ->where('stats.matched', 20)
                ->where('stats.p95_latency_ms', 190)
                ->where('stats.avg_latency_ms', 105));
    } finally {
        foreach ($turns as $turn) {
            $turn->delete();
        }
        $key->delete();
    }
});

it('scopes prev/next neighbors to the active probe filters', function () {
    $admin = wiseLogAdmin();
    $key = WiseApiKey::generate('log-nav')['key'];
    $suffix = uniqid('nav-');

    $olderMessenger = wiseLogTurn($key, [
        'channel' => 'messenger',
        'conversation_id' => $suffix.'-m1',
        'text' => 'older messenger',
    ]);
    $playground = wiseLogTurn($key, [
        'channel' => 'playground',
        'conversation_id' => $suffix.'-pg',
        'text' => 'playground noise',
    ]);
    $newerMessenger = wiseLogTurn($key, [
        'channel' => 'messenger',
        'conversation_id' => $suffix.'-m2',
        'text' => 'newer messenger',
    ]);

    try {
        expect($playground->id)->toBeGreaterThan($olderMessenger->id)
            ->and($newerMessenger->id)->toBeGreaterThan($playground->id);

        $this->actingAs($admin)
            ->getJson(route('wiseAi.log.turn', [
                'turn' => $olderMessenger->id,
                'channel' => 'messenger',
                'key_id' => $key->id,
                'hours' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('nav.scoped', true)
            ->assertJsonPath('nav.prev_id', null)
            ->assertJsonPath('nav.next_id', $newerMessenger->id);

        $this->actingAs($admin)
            ->getJson(route('wiseAi.log.turn', [
                'turn' => $newerMessenger->id,
                'channel' => 'messenger',
                'key_id' => $key->id,
                'hours' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('nav.prev_id', $olderMessenger->id)
            ->assertJsonPath('nav.next_id', null);
    } finally {
        $olderMessenger->delete();
        $playground->delete();
        $newerMessenger->delete();
        $key->delete();
    }
});

it('loads channel and source facets from the probe window not the whole table', function () {
    $admin = wiseLogAdmin();
    $key = WiseApiKey::generate('log-facets')['key'];
    $suffix = uniqid('facet-');

    $inWindow = wiseLogTurn($key, [
        'channel' => 'facet_live_'.$suffix,
        'conversation_id' => $suffix.'-live',
        'text' => 'in window',
        'decision' => [
            'action' => 'suggest_reply',
            'intent' => 'test',
            'confidence' => 70,
            'source' => 'facet_source_live',
            'suggested_reply' => 'ok',
        ],
    ]);
    $old = wiseLogTurn($key, [
        'channel' => 'facet_old_'.$suffix,
        'conversation_id' => $suffix.'-old',
        'text' => 'outside window',
        'decision' => [
            'action' => 'clarify',
            'intent' => 'test',
            'confidence' => 10,
            'source' => 'facet_source_old',
            'suggested_reply' => 'hmm',
        ],
    ]);
    $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    try {
        $this->actingAs($admin)
            ->get(route('wiseAi.log', [
                'key_id' => $key->id,
                'hours' => 24,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WiseAi/Log')
                ->where('channels', fn ($channels) => collect($channels)->contains('facet_live_'.$suffix)
                    && ! collect($channels)->contains('facet_old_'.$suffix))
                ->where('sources', fn ($sources) => collect($sources)->contains('facet_source_live')
                    && ! collect($sources)->contains('facet_source_old')));
    } finally {
        $inWindow->delete();
        $old->delete();
        $key->delete();
    }
});
