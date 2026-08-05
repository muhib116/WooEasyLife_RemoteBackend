<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\Search\DatabaseKnowledgeSearchDriver;
use App\WiseAi\Knowledge\Search\InMemoryKnowledgeSearchDriver;
use App\WiseAi\Knowledge\Search\KnowledgeSearchDocument;
use App\WiseAi\Knowledge\Search\KnowledgeSearchManager;
use App\WiseAi\Knowledge\Search\MeilisearchKnowledgeSearchDriver;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Facades\Http;

it('builds search documents without answer field', function () {
    $item = new WiseKnowledgeItem([
        'title' => 'Payment FAQ',
        'question' => 'bkash?',
        'answer' => 'Secret fee 60 টাকা should never be indexed.',
        'keywords' => ['bkash', 'payment'],
        'status' => 'published',
        'type' => KnowledgeSchema::KIND_FAQ,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'wise_api_key_id' => 1,
    ]);
    $item->id = 99;
    $item->match_text = 'payment faq bkash';

    $doc = KnowledgeSearchDocument::fromItem($item);
    expect($doc)->not->toBeNull()
        ->and($doc)->not->toHaveKey('answer')
        ->and(KnowledgeSearchDocument::SEARCHABLE)->not->toContain('answer');

    KnowledgeSearchDocument::assertNoSearchableAnswer($doc);
});

it('resolves published knowledge with default database driver', function () {
    config(['wise_ai.knowledge_search.driver' => 'database']);
    app(KnowledgeSearchManager::class)->useDriver(null);

    $key = WiseApiKey::generate('knowledge-search-db')['key'];
    WiseKnowledgeItem::create([
        'wise_api_key_id' => $key->id,
        'type' => KnowledgeSchema::KIND_FAQ,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'title' => 'Delivery charge policy',
        'question' => 'delivery charge koto?',
        'answer' => 'এলাকা বললে দেখে চার্জ জানাই; আন্দাজ করে বলব না।',
        'keywords' => ['delivery', 'charge', 'ডেলিভারি'],
        'status' => 'published',
        'version' => 1,
    ]);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'delivery charge koto?',
        'channel' => 'test',
        'conversation_id' => 'knowledge-search-db',
    ]));

    expect($run['decision']['source'] ?? null)->toBe('knowledge')
        ->and($run['decision']['suggested_reply'] ?? null)->toContain('এলাকা')
        ->and($run['decision']['suggested_reply'])->not->toMatch('/\d+\s*(?:tk|taka|টাকা)/iu');

    $key->delete();
});

it('uses inmemory prefilter IDs then returns only published answer', function () {
    config(['wise_ai.knowledge_search.driver' => 'inmemory']);
    InMemoryKnowledgeSearchDriver::reset();
    $manager = app(KnowledgeSearchManager::class);
    $manager->useDriver(null);

    $key = WiseApiKey::generate('knowledge-search-mem')['key'];
    $item = WiseKnowledgeItem::create([
        'wise_api_key_id' => $key->id,
        'type' => KnowledgeSchema::KIND_FAQ,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'title' => 'Bkash payment rules',
        'question' => 'bkash e payment kora jabe?',
        'answer' => 'কোন মেথড চান বলবেন? নিয়ম দেখে জানাই; চার্জ আন্দাজ করে বলব না।',
        'keywords' => ['bkash', 'payment', 'বিকাশ'],
        'status' => 'published',
        'version' => 1,
    ]);

    // saved hook already upserted; ensure index has the row
    expect(app(InMemoryKnowledgeSearchDriver::class)->search('bkash payment', [
        'status' => 'published',
        'types' => KnowledgeSchema::groundableKinds(),
        'wise_api_key_id' => $key->id,
        'exclude_platform' => true,
    ], 10))->toContain($item->id);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'bkash e payment kora jabe?',
        'channel' => 'test',
        'conversation_id' => 'knowledge-search-mem',
    ]));

    expect($run['decision']['source'] ?? null)->toBe('knowledge')
        ->and($run['decision']['suggested_reply'] ?? null)->toBe($item->answer)
        ->and($run['decision']['suggested_reply'])->not->toMatch('/\d+\s*(?:tk|taka|টাকা)/iu');

    $manager->useDriver(null);
    config(['wise_ai.knowledge_search.driver' => 'database']);
    InMemoryKnowledgeSearchDriver::reset();
    $key->delete();
});

it('falls back to SQL LIKE when search driver is unavailable', function () {
    config(['wise_ai.knowledge_search.driver' => 'meilisearch']);
    config(['wise_ai.knowledge_search.meilisearch.host' => null]);

    $down = new class implements \App\WiseAi\Knowledge\Search\KnowledgeSearchDriver
    {
        public function isAvailable(): bool
        {
            return false;
        }

        public function search(string $query, array $filters, int $limit): array
        {
            return [];
        }

        public function upsert(array $document): void {}

        public function delete(int $id): void {}

        public function clear(): void {}
    };

    $manager = app(KnowledgeSearchManager::class);
    $manager->useDriver($down);

    $key = WiseApiKey::generate('knowledge-search-fallback')['key'];
    WiseKnowledgeItem::create([
        'wise_api_key_id' => $key->id,
        'type' => KnowledgeSchema::KIND_FAQ,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'title' => 'COD area ask',
        'question' => 'cod available?',
        'answer' => 'কোন এলাকায় COD লাগবে বলবেন? আন্দাজ করে হ্যাঁ/না বলব না।',
        'keywords' => ['cod', 'cash on delivery'],
        'status' => 'published',
        'version' => 1,
    ]);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'cod available?',
        'channel' => 'test',
        'conversation_id' => 'knowledge-search-fallback',
    ]));

    expect($run['decision']['source'] ?? null)->toBe('knowledge')
        ->and($run['decision']['suggested_reply'] ?? null)->toContain('এলাকায়');

    $manager->useDriver(null);
    config(['wise_ai.knowledge_search.driver' => 'database']);
    $key->delete();
});

it('meilisearch driver returns empty IDs when HTTP fails', function () {
    config([
        'wise_ai.knowledge_search.driver' => 'meilisearch',
        'wise_ai.knowledge_search.meilisearch.host' => 'http://127.0.0.1:9770',
        'wise_ai.knowledge_search.meilisearch.key' => '',
        'wise_ai.knowledge_search.meilisearch.index' => 'wise_knowledge_items_test',
    ]);

    Http::fake([
        'http://127.0.0.1:9770/*' => Http::response(['message' => 'down'], 503),
    ]);

    $driver = app(MeilisearchKnowledgeSearchDriver::class);
    expect($driver->isAvailable())->toBeTrue()
        ->and($driver->search('delivery', ['status' => 'published'], 10))->toBe([]);

    config(['wise_ai.knowledge_search.driver' => 'database']);
});

it('wise:knowledge-reindex is no-op for database driver', function () {
    config(['wise_ai.knowledge_search.driver' => 'database']);
    app(KnowledgeSearchManager::class)->useDriver(null);

    $this->artisan('wise:knowledge-reindex')
        ->expectsOutputToContain('database')
        ->assertSuccessful();

    expect(app(KnowledgeSearchManager::class)->driver())->toBeInstanceOf(DatabaseKnowledgeSearchDriver::class);
});
