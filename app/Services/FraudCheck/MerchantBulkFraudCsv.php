<?php

namespace App\Services\FraudCheck;

use App\Models\BulkFraudBatch;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MerchantBulkFraudCsv
{
    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}
     */
    public function table(BulkFraudBatch $batch): array
    {
        $batch->loadMissing('items');

        $headers = [
            'Phone Number',
            'Status',
            'Total Orders',
            'Successful',
            'Cancelled',
            'Success Rate',
        ];

        $rows = [];
        foreach ($this->bySuccessRate($batch) as $item) {
            $rows[] = [
                $item->phone,
                $this->statusLabel((string) $item->status),
                (int) $item->total_orders,
                (int) $item->received,
                (int) $item->cancelled,
                $this->percent($item->success_rate),
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    public function download(BulkFraudBatch $batch): StreamedResponse
    {
        $table = $this->table($batch);
        $filename = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $batch->name), '-.');
        if ($filename === '') {
            $filename = 'courier-history';
        }

        return response()->streamDownload(function () use ($table): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $table['headers']);
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map($this->csvSafe(...), $row));
            }
            fclose($out);
        }, substr($filename, 0, 80).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Highest success rate first. Numbers with no rate stay at the bottom,
     * and equal rates keep the original batch order.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\BulkFraudBatchItem>
     */
    private function bySuccessRate(BulkFraudBatch $batch)
    {
        return $batch->items
            ->sort(function ($left, $right): int {
                $leftMissing = $left->success_rate === null || $left->success_rate === '';
                $rightMissing = $right->success_rate === null || $right->success_rate === '';

                if ($leftMissing !== $rightMissing) {
                    return $leftMissing <=> $rightMissing;
                }

                if (! $leftMissing && (float) $left->success_rate !== (float) $right->success_rate) {
                    return (float) $right->success_rate <=> (float) $left->success_rate;
                }

                return ((int) $left->position) <=> ((int) $right->position);
            })
            ->values();
    }

    private function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function percent(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 1).'%';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'found' => 'Found',
            'not_found' => 'Not found',
            'invalid' => 'Invalid',
            'pending', 'checking' => 'Waiting',
            'error' => 'Failed',
            default => $status,
        };
    }

}
