<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\SteadfastFraudApiCredentialStore;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Console\Command;

class RetestSteadfastKeysCommand extends Command
{
    protected $signature = 'fraud-check:retest-steadfast-keys';

    protected $description = 'Ask one Steadfast key that still shows a passing error, and clear it when Steadfast answers';

    public function handle(SteadfastFraudApiCredentialStore $store, SteadfastFraudChecker $checker): int
    {
        $account = $store->healCandidate();

        if ($account === null || ! $store->claimHeal()) {
            return self::SUCCESS;
        }

        $result = $checker->probeApiKey($account['api_key'], $account['secret_key']);
        $store->rememberProbe($account['api_key'], $result);

        if (! empty($result['ok'])) {
            $this->info('Steadfast answered. The saved error was cleared.');

            return self::SUCCESS;
        }

        $this->info('Steadfast did not clear this key yet. It will be asked again after it rests.');

        return self::SUCCESS;
    }
}
