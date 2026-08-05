<?php

namespace App\Console\Commands;

use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Knowledge\Seed\PlatformKnowledgeSeeder;
use App\WiseAi\Knowledge\Seed\PlatformScriptCatalog;
use App\WiseAi\Knowledge\Seed\SeedQualityScorer;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Language\RegionalKnowledgeSeeder;
use Illuminate\Console\Command;

/**
 * Validate + seed platform/region knowledge (Trust First — no invented fees).
 */
class WiseSeedKnowledgeCommand extends Command
{
    protected $signature = 'wise:seed-knowledge
        {--validate-only : Run validators without writing DB}';

    protected $description = 'Seed validated platform + regional knowledge as drafts (human Publish required)';

    public function handle(
        KnowledgeSeedValidator $validator,
        SeedQualityScorer $quality,
        PlatformKnowledgeSeeder $platform,
        RegionalKnowledgeSeeder $regional,
    ): int {
        $platformCatalog = PlatformScriptCatalog::items();
        $regionalCatalog = $regional->catalog();
        $errors = array_merge(
            $validator->validateCatalog($platformCatalog, KnowledgeSchema::SCOPE_PLATFORM),
            $validator->validateCatalog($regionalCatalog, KnowledgeSchema::SCOPE_REGION),
            $validator->validateRegionalLexicon(),
        );
        if ($errors !== []) {
            foreach ($errors as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        $this->info('Validation OK — platform catalog v'.PlatformScriptCatalog::VERSION
            .' ('.count($platformCatalog).' scripts); regional lexicon unique.');

        $scores = [
            'platform' => $quality->scoreCatalog($platformCatalog, KnowledgeSchema::SCOPE_PLATFORM),
            'regional' => $quality->scoreCatalog($regionalCatalog, KnowledgeSchema::SCOPE_REGION),
        ];
        foreach ($scores as $name => $result) {
            $this->line(sprintf(
                '%s copy quality: %0.2f/10 (%d scripts)',
                ucfirst($name),
                $result['score'],
                $result['items'],
            ));
            foreach ($result['failures'] as $slug => $issues) {
                $this->error($name.' '.$slug.': '.implode('; ', $issues));
            }
        }
        if (! $scores['platform']['perfect'] || ! $scores['regional']['perfect']) {
            $this->error('Seed write blocked: copy quality must meet every defined criterion (10.00/10).');

            return self::FAILURE;
        }

        if ($this->option('validate-only')) {
            return self::SUCCESS;
        }

        $p = $platform->run();
        $this->info(sprintf('Platform knowledge upserted as drafts: %d (v%s)', $p['upserted'], $p['version']));

        $r = $regional->run();
        $this->info(sprintf(
            'Regional knowledge upserted as drafts: %d across %s',
            $r['upserted'],
            implode(', ', $r['regions'])
        ));
        $this->comment('Review → Publish in Wise AI → Knowledge (Seeded review / bulk publish).');

        return self::SUCCESS;
    }
}
