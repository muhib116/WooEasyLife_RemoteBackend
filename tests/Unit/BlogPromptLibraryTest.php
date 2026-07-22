<?php

namespace Tests\Unit;

use App\Services\BlogAi\BlogPromptLibrary;
use Tests\TestCase;

class BlogPromptLibraryTest extends TestCase
{
    public function test_structure_contract_loads_playbook_and_skeleton(): void
    {
        $library = app(BlogPromptLibrary::class);

        $howto = $library->structureContract('howto');
        $this->assertStringContainsString('Source-of-truth hierarchy', $howto);
        $this->assertStringContainsString('Skeleton — howto', $howto);
        $this->assertStringContainsString('`steps`', $howto);

        $comparison = $library->skeleton('comparison');
        $this->assertStringContainsString('comparison', $comparison);
        $this->assertStringContainsString('`when_to_choose`', $comparison);

        $fallback = $library->skeleton('unknown_type');
        $this->assertStringContainsString('Skeleton — howto', $fallback);
    }

    public function test_playbook_defines_canonical_flow(): void
    {
        $playbook = app(BlogPromptLibrary::class)->playbook();

        $this->assertStringContainsString('দ্রুত উত্তর', $playbook);
        $this->assertStringContainsString('এআই সারাংশ', $playbook);
        $this->assertStringContainsString('product_brief', $playbook);
        $this->assertStringContainsString('skeleton wins', strtolower($playbook));
    }
}
