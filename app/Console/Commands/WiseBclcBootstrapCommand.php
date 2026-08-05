<?php

namespace App\Console\Commands;

use App\WiseAi\Language\BclcBootstrap;
use App\WiseAi\Language\PackCompiler;
use App\Models\WiseAi\WiseLanguagePack;
use Illuminate\Console\Command;

class WiseBclcBootstrapCommand extends Command
{
    protected $signature = 'wise:bclc-bootstrap
        {--compile-only : Recompile existing published packs without reseeding surfaces}';

    protected $description = 'Seed/compile BCLC Core + Commerce + Messenger + Regional packs';

    public function handle(BclcBootstrap $bootstrap, PackCompiler $compiler): int
    {
        if ($this->option('compile-only')) {
            $packs = WiseLanguagePack::query()->orderBy('id')->get();
            if ($packs->isEmpty()) {
                $this->error('No packs found. Run without --compile-only first.');

                return self::FAILURE;
            }
            foreach ($packs as $pack) {
                $result = $compiler->compileAndPublish($pack);
                $this->line(sprintf(
                    '%s %s hash=%s %s',
                    $result['created'] ? 'compiled' : 'unchanged',
                    $pack->slug,
                    substr($result['content_hash'], 0, 12),
                    $pack->semver
                ));
            }

            return self::SUCCESS;
        }

        $result = $bootstrap->run();
        $this->info('BCLC packs: '.implode(', ', $result['packs']));
        foreach ($result['artifacts'] as $row) {
            $this->line(sprintf(
                '%s %s hash=%s',
                $row['created'] ? 'compiled' : 'unchanged',
                $row['slug'],
                substr($row['hash'], 0, 12)
            ));
        }

        $knowledge = app(\App\WiseAi\Language\RegionalKnowledgeSeeder::class)->run();
        $this->info(sprintf(
            'Regional knowledge: %d scripts across %s',
            $knowledge['upserted'],
            implode(', ', $knowledge['regions'])
        ));

        $platform = app(\App\WiseAi\Knowledge\Seed\PlatformKnowledgeSeeder::class)->run();
        $this->info(sprintf(
            'Platform knowledge: %d scripts (catalog v%s)',
            $platform['upserted'],
            $platform['version']
        ));

        return self::SUCCESS;
    }
}
