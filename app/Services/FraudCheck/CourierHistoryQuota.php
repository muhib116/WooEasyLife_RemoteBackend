<?php

namespace App\Services\FraudCheck;

use App\Models\CourierHistoryUse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class CourierHistoryQuota
{
    /**
     * @return array{enabled: bool, daily_limit: int, used_today: int, remaining: int}
     */
    public function snapshot(User $merchant): array
    {
        $enabled = (bool) $merchant->courier_history_enabled;
        $limit = max(0, (int) $merchant->courier_history_daily_limit);

        try {
            $used = $this->usedToday($merchant);
        } catch (QueryException $exception) {
            if (! $this->schemaMissing($exception)) {
                throw $exception;
            }

            $enabled = false;
            $limit = 0;
            $used = 0;
        }

        return [
            'enabled' => $enabled,
            'daily_limit' => $limit,
            'used_today' => $used,
            'remaining' => $enabled ? max(0, $limit - $used) : 0,
        ];
    }

    public function allows(User $merchant, string $phone): bool
    {
        if (! $merchant->courier_history_enabled) {
            return false;
        }

        if ($this->alreadyCounted($merchant, $phone)) {
            return true;
        }

        return $this->usedToday($merchant) < (int) $merchant->courier_history_daily_limit;
    }

    public function alreadyCounted(User $merchant, string $phone): bool
    {
        return CourierHistoryUse::query()
            ->where('user_id', $merchant->id)
            ->where('phone', $phone)
            ->where('checked_on', $this->today()->toDateString())
            ->exists();
    }

    public function usedToday(User $merchant): int
    {
        return CourierHistoryUse::query()
            ->where('user_id', $merchant->id)
            ->where('checked_on', $this->today()->toDateString())
            ->count();
    }

    public function record(User $merchant, string $phone): void
    {
        try {
            CourierHistoryUse::query()->firstOrCreate([
                'user_id' => $merchant->id,
                'phone' => $phone,
                'checked_on' => $this->today()->toDateString(),
            ]);
        } catch (QueryException) {
            // Another request recorded this number for today.
        }
    }

    private function today(): Carbon
    {
        return Carbon::now('Asia/Dhaka');
    }

    private function schemaMissing(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? '');

        return in_array($state, ['42S02', '42S22'], true);
    }
}
