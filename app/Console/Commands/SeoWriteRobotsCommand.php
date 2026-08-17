<?php

namespace App\Console\Commands;

use App\Http\Controllers\App\RobotsController;
use App\Support\SeoRobotsTxt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SeoWriteRobotsCommand extends Command
{
    protected $signature = 'seo:write-robots';

    protected $description = 'Write public/robots.txt (static file for LiteSpeed) and refresh the response cache';

    public function handle(): int
    {
        $body = SeoRobotsTxt::body();
        $path = SeoRobotsTxt::syncPublicFile($body);
        Cache::put(RobotsController::CACHE_KEY, $body, RobotsController::CACHE_SECONDS);

        $this->info('Wrote '.$path.' ('.strlen($body).' bytes).');

        return self::SUCCESS;
    }
}
