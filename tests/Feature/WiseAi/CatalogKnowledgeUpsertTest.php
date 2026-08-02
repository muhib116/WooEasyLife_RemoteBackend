<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\CatalogKnowledgeUpsertor;
use App\WiseAi\Knowledge\KnowledgeSchema;

it('upserts catalog offers as draft and unpublishes on content change', function () {
    $gen = WiseApiKey::generate('catalog-upsert');
    $key = $gen['key'];
    $plain = $gen['plain'];

    $first = $this->postJson('/api/wise/v1/knowledge/upsert', [
        'external_id' => 'wc-42',
        'platform' => 'woocommerce',
        'title' => 'Blue Shirt',
        'answer' => 'Price: 1500 BDT',
        'offer_kind' => 'physical',
        'sku' => 'SHIRT-42',
        'keywords' => ['shirt', 'blue'],
    ], [
        'Authorization' => 'Bearer '.$plain,
    ]);

    $first->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('created', true)
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('version', 1)
        ->assertJsonPath('external_id', 'wc-42');

    $id = (int) $first->json('knowledge_id');
    WiseKnowledgeItem::query()->whereKey($id)->update(['status' => 'published']);

    $noop = $this->postJson('/api/wise/v1/knowledge/upsert', [
        'external_id' => 'wc-42',
        'platform' => 'woocommerce',
        'title' => 'Blue Shirt',
        'answer' => 'Price: 1500 BDT',
        'offer_kind' => 'physical',
        'sku' => 'SHIRT-42',
        'keywords' => ['blue', 'shirt'],
    ], [
        'Authorization' => 'Bearer '.$plain,
    ]);

    $noop->assertOk()
        ->assertJsonPath('created', false)
        ->assertJsonPath('changed', false)
        ->assertJsonPath('unpublished', false)
        ->assertJsonPath('status', 'published')
        ->assertJsonPath('version', 1);

    $changed = $this->postJson('/api/wise/v1/knowledge/upsert', [
        'external_id' => 'wc-42',
        'platform' => 'woocommerce',
        'title' => 'Blue Shirt',
        'answer' => 'Price: 1600 BDT',
        'offer_kind' => 'physical',
        'sku' => 'SHIRT-42',
        'keywords' => ['shirt', 'blue'],
    ], [
        'Authorization' => 'Bearer '.$plain,
    ]);

    $changed->assertOk()
        ->assertJsonPath('created', false)
        ->assertJsonPath('changed', true)
        ->assertJsonPath('unpublished', true)
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('version', 2)
        ->assertJsonPath('knowledge_id', $id);

    expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->where('type', KnowledgeSchema::KIND_OFFER)->count())
        ->toBe(1);

    WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});

it('scopes upsert identity by platform', function () {
    $key = WiseApiKey::generate('catalog-platform')['key'];
    $upsertor = app(CatalogKnowledgeUpsertor::class);

    $a = $upsertor->upsert($key, [
        'external_id' => 'shared-1',
        'platform' => 'woocommerce',
        'title' => 'WC item',
        'answer' => '100',
        'offer_kind' => 'physical',
    ]);
    $b = $upsertor->upsert($key, [
        'external_id' => 'shared-1',
        'platform' => 'shopify',
        'title' => 'Shopify item',
        'answer' => '200',
        'offer_kind' => 'physical',
    ]);

    expect($a['created'])->toBeTrue()
        ->and($b['created'])->toBeTrue()
        ->and($a['item']->id)->not->toBe($b['item']->id)
        ->and(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->count())->toBe(2);

    WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});

it('rejects catalog upsert without api key', function () {
    $this->postJson('/api/wise/v1/knowledge/upsert', [
        'external_id' => 'x',
        'title' => 'T',
        'answer' => 'A',
    ])->assertUnauthorized();
});

it('preserves non-catalog meta keys on upsert change', function () {
    $key = WiseApiKey::generate('catalog-meta-keep')['key'];
    $upsertor = app(CatalogKnowledgeUpsertor::class);

    $first = $upsertor->upsert($key, [
        'external_id' => 'meta-1',
        'platform' => 'woocommerce',
        'title' => 'Meta Item',
        'answer' => '100',
        'offer_kind' => 'physical',
    ]);
    $item = $first['item'];
    $item->update([
        'meta' => array_merge($item->meta ?? [], ['merchant_note' => 'keep-me', 'region' => 'dhaka']),
        'status' => 'published',
    ]);

    $second = $upsertor->upsert($key, [
        'external_id' => 'meta-1',
        'platform' => 'woocommerce',
        'title' => 'Meta Item',
        'answer' => '110',
        'offer_kind' => 'physical',
    ]);

    expect($second['changed'])->toBeTrue()
        ->and($second['unpublished'])->toBeTrue()
        ->and($second['item']->meta['merchant_note'] ?? null)->toBe('keep-me')
        ->and($second['item']->meta['region'] ?? null)->toBe('dhaka')
        ->and($second['item']->meta['offer_kind'] ?? null)->toBe('physical');

    WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});
