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
            'Phone',
            'Delivery Status',
            'Total',
            'Received',
            'Cancelled',
            'Partial',
            'Rate',
            'Risk',
        ];

        $rows = [];
        foreach ($batch->items as $item) {
            $rows[] = [
                $item->phone,
                $this->statusLabel((string) $item->status),
                (int) $item->total_orders,
                (int) $item->received,
                (int) $item->cancelled,
                (int) $item->partial,
                $this->percent($item->success_rate),
                $this->riskLabel($item->risk),
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

        return ((int) $value).'%';
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

    private function riskLabel(?string $risk): string
    {
        return match ($risk) {
            'safe' => 'Safe',
            'review' => 'Review',
            'high' => 'High risk',
            default => '',
        };
    }
}
