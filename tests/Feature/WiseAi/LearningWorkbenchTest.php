<?php

use App\Models\User;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\KnowledgeResolver;
use App\WiseAi\Learning\LearningInbox;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Facades\Hash;

it('exposes gap_assist suggested_reply on learning gap feed rows', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('learn-gap-assist')['key'];

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'delivery charge koto?',
            'channel' => 'test',
            'conversation_id' => 'learn-gap-assist-1',
        ]));

        expect($run['decision']['gap'] ?? false)->toBeTrue()
            ->and(trim((string) ($run['decision']['suggested_reply'] ?? '')))->not->toBe('');

        $row = collect(app(LearningInbox::class)->feed('gap', 40))
            ->firstWhere('turn_id', $run['turn']->id);

        expect($row)->not->toBeNull()
            ->and(trim((string) ($row['suggested_reply'] ?? '')))->not->toBe('')
            ->and($row['suggested_reply'])->toBe($run['decision']['suggested_reply']);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('drafts from gap using auto-draft merchant scope and can publish_now', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('learn-gap-draft')['key'];
    $admin = User::create([
        'name' => 'Wise Learning Admin',
        'email' => 'wise-learning-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'bkash e payment kora jabe?',
            'channel' => 'test',
            'conversation_id' => 'learn-gap-draft-1',
        ]));
        $turn = $run['turn'];
        expect($turn->gap)->toBeTrue()
            ->and($turn->gap_auto_draft_id)->not->toBeNull();

        $this->actingAs($admin)
            ->postJson(route('wiseAi.gaps.draft', ['turn' => $turn->id]), [
                'type' => 'faq',
                // Client may ask platform, but auto-draft stays merchant for this key.
                'scope' => 'platform',
                'title' => 'Payment method clarify',
                'question' => 'bkash e payment kora jabe?',
                'answer' => 'কোন মেথডে পেমেন্ট করতে চান বলবেন? নিয়ম দেখে জানাই; আন্দাজ করে চার্জ বলব না।',
                'keywords' => ['bkash', 'payment'],
                'publish_now' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('published', true);

        $item = WiseKnowledgeItem::query()->findOrFail($turn->fresh()->gap_knowledge_id);
        expect($item->status)->toBe('published')
            ->and($item->scope)->toBe(KnowledgeSchema::SCOPE_MERCHANT)
            ->and($item->wise_api_key_id)->toBe($key->id);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('learning page props include seeded_drafts and can_publish', function () {
    $admin = User::create([
        'name' => 'Wise Learning Props',
        'email' => 'wise-learning-props-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('wiseAi.learning', ['kind' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('WiseAi/Learning')
            ->has('seeded_drafts')
            ->where('can_publish', true)
            ->where('can_edit', true));
});
