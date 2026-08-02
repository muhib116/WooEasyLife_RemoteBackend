<?php

use App\Models\WiseAi\WiseApiKey;
use App\WiseAi\DecideEngine;
use App\WiseAi\Knowledge\CatalogKnowledgeUpsertor;

it('pings without creating a wise turn', function () {
    $gen = WiseApiKey::generate('ping-flywheel');
    $plain = $gen['plain'];
    $key = $gen['key'];

    $before = \App\Models\WiseAi\WiseTurn::query()->where('wise_api_key_id', $key->id)->count();

    $this->getJson('/api/wise/v1/ping', [
        'Authorization' => 'Bearer '.$plain,
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('brain_version', DecideEngine::BRAIN_VERSION)
        ->assertJsonPath('catalog_schema_version', CatalogKnowledgeUpsertor::SCHEMA_VERSION)
        ->assertJsonPath('key.id', $key->id);

    expect(\App\Models\WiseAi\WiseTurn::query()->where('wise_api_key_id', $key->id)->count())->toBe($before);

    $this->getJson('/api/wise/v1/ping')->assertUnauthorized();

    $key->delete();
});
