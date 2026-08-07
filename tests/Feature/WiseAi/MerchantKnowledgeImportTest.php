<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\MerchantKnowledgeImporter;

it('imports messenger faq and policy drafts idempotently', function () {
    $bundle = WiseApiKey::generate('messenger-knowledge-import');
    $key = $bundle['key'];
    $plain = $bundle['plain'];

    try {
        $first = $this->postJson('/api/wise/v1/knowledge/import', [
            'items' => [
                [
                    'messenger_key' => 'wc:store:faq:0',
                    'type' => 'faq',
                    'scope' => 'merchant',
                    'title' => 'COD আছে?',
                    'question' => 'Cash on Delivery আছে?',
                    'answer' => 'হ্যাঁ, সারা বাংলাদেশে COD আছে।',
                    'keywords' => ['cod', 'ক্যাশ'],
                    'platform' => 'woocommerce',
                    'chunk' => 'faq',
                ],
                [
                    'messenger_key' => 'wc:42:faq:0',
                    'type' => 'faq',
                    'scope' => 'offer',
                    'external_id' => '42',
                    'title' => 'Size guide',
                    'question' => 'সাইজ কীভাবে নেব?',
                    'answer' => 'চেস্ট মেপে চার্ট দেখুন।',
                    'keywords' => ['size', 'সাইজ'],
                    'platform' => 'woocommerce',
                    'chunk' => 'faq',
                    'wc_product_id' => 42,
                ],
                [
                    'messenger_key' => 'wc:store:delivery',
                    'type' => 'policy',
                    'scope' => 'merchant',
                    'title' => 'Delivery notes',
                    'answer' => 'ঢাকায় ১–২ দিন।',
                    'platform' => 'woocommerce',
                    'chunk' => 'delivery',
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$plain,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', 3)
            ->assertJsonPath('changed', 0);

        expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->count())->toBe(3);

        $offerFaq = WiseKnowledgeItem::query()
            ->where('wise_api_key_id', $key->id)
            ->where('meta->messenger_key', 'wc:42:faq:0')
            ->first();
        expect($offerFaq)->not->toBeNull()
            ->and($offerFaq->scope)->toBe(KnowledgeSchema::SCOPE_OFFER)
            ->and($offerFaq->external_id)->toBe('42')
            ->and($offerFaq->status)->toBe('draft');

        $noop = $this->postJson('/api/wise/v1/knowledge/import', [
            'items' => [
                [
                    'messenger_key' => 'wc:store:faq:0',
                    'type' => 'faq',
                    'scope' => 'merchant',
                    'title' => 'COD আছে?',
                    'question' => 'Cash on Delivery আছে?',
                    'answer' => 'হ্যাঁ, সারা বাংলাদেশে COD আছে।',
                    'keywords' => ['cod', 'ক্যাশ'],
                    'platform' => 'woocommerce',
                    'chunk' => 'faq',
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$plain,
        ])
            ->assertOk()
            ->assertJsonPath('unchanged', 1)
            ->assertJsonPath('created', 0);

        $changed = $this->postJson('/api/wise/v1/knowledge/import', [
            'items' => [
                [
                    'messenger_key' => 'wc:store:faq:0',
                    'type' => 'faq',
                    'scope' => 'merchant',
                    'title' => 'COD আছে?',
                    'question' => 'Cash on Delivery আছে?',
                    'answer' => 'হ্যাঁ — আপডেট করা উত্তর।',
                    'keywords' => ['cod'],
                    'platform' => 'woocommerce',
                    'chunk' => 'faq',
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$plain,
        ])
            ->assertOk()
            ->assertJsonPath('changed', 1);

        $row = WiseKnowledgeItem::query()
            ->where('wise_api_key_id', $key->id)
            ->where('meta->messenger_key', 'wc:store:faq:0')
            ->first();
        expect($row->answer)->toBe('হ্যাঁ — আপডেট করা উত্তর।')
            ->and($row->status)->toBe('draft')
            ->and((int) $row->version)->toBeGreaterThan(1);

        expect($first->json('schema_version'))->toBe(MerchantKnowledgeImporter::SCHEMA_VERSION)
            ->and($noop->json('ok'))->toBeTrue()
            ->and($changed->json('ok'))->toBeTrue();
    } finally {
        WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->delete();
        $key->delete();
    }
});
