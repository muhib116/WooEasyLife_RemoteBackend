<?php

namespace App\Services\OrderIntelligence;

use Illuminate\Support\Facades\Log;

class FraudCheckDecisionLog
{
    /** @var list<array{at: string, step: string, message: string, context: array<string, mixed>}> */
    private array $steps = [];

    private bool $enabled;

    private bool $writeLaravelLog;

    public function __construct(bool $enabled = false, bool $writeLaravelLog = false)
    {
        $this->enabled = $enabled;
        $this->writeLaravelLog = $writeLaravelLog;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function add(string $step, string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $entry = [
            'at' => now()->format('H:i:s.v'),
            'step' => $step,
            'message' => $message,
            'context' => $context,
        ];

        $this->steps[] = $entry;

        if ($this->writeLaravelLog) {
            Log::debug('[fraud-check] '.$step.': '.$message, $context);
        }
    }

    /**
     * @return array{enabled: bool, steps: list<array{at: string, step: string, message: string, context: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'steps' => $this->steps,
        ];
    }
}
