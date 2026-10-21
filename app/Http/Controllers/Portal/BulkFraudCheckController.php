<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BulkFraudBatch;
use App\Models\BulkFraudBatchItem;
use App\Services\FraudCheck\CourierHistoryQuota;
use App\Services\FraudCheck\MerchantBulkFraudCheck;
use App\Services\FraudCheck\MerchantBulkFraudCsv;
use App\Services\MerchantPortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkFraudCheckController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Portal/FraudCheck/Bulk', [
            'maxNumbers' => (int) config('fraud_check.bulk_max_numbers', 1000),
        ]);
    }

    public function searchPage(): Response
    {
        return Inertia::render('Portal/FraudCheck/Search');
    }

    public function search(
        Request $request,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $merchant = $portalContext->resolveMerchant($request->user());

        return response()->json($this->withQuota(
            $request,
            $bulk->search($request, $merchant, $data['phone']),
        ));
    }

    public function batches(Request $request, MerchantPortalContext $portalContext): Response
    {
        $merchant = $portalContext->resolveMerchant($request->user());

        $batches = BulkFraudBatch::query()
            ->where('user_id', $merchant->id)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BulkFraudBatch $batch): array => [
                'id' => $batch->id,
                'name' => $batch->name,
                'status' => $batch->status,
                'unique_count' => (int) $batch->unique_count,
                'duplicates_removed' => (int) $batch->duplicates_removed,
                'created_at' => optional($batch->created_at)?->timezone('Asia/Dhaka')->format('d M Y, h:i A'),
            ]);

        return Inertia::render('Portal/FraudCheck/Batches', [
            'batches' => $batches,
        ]);
    }

    public function store(
        Request $request,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'phones' => ['required', 'string', 'max:40000'],
        ]);

        $merchant = $portalContext->resolveMerchant($request->user());
        $batch = $bulk->start($merchant, trim((string) ($data['name'] ?? '')), $data['phones']);

        return response()->json($this->withQuota($request, $this->payload($bulk, $batch)));
    }

    public function show(
        Request $request,
        BulkFraudBatch $batch,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): Response|JsonResponse {
        $this->owns($request, $portalContext, $batch);
        $payload = $this->withQuota($request, $this->payload($bulk, $batch->load('items')));

        if ($request->header('X-Inertia')) {
            return Inertia::render('Portal/FraudCheck/Batch', $payload);
        }

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Portal/FraudCheck/Batch', $payload);
    }

    public function next(
        Request $request,
        BulkFraudBatch $batch,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $this->owns($request, $portalContext, $batch);

        return response()->json($this->withQuota($request, $bulk->advance($request, $batch)));
    }

    public function stop(
        Request $request,
        BulkFraudBatch $batch,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $this->owns($request, $portalContext, $batch);

        return response()->json($this->payload($bulk, $bulk->stop($batch)));
    }

    public function resume(
        Request $request,
        BulkFraudBatch $batch,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $this->owns($request, $portalContext, $batch);

        return response()->json($this->payload($bulk, $bulk->resume($batch)));
    }

    public function retry(
        Request $request,
        BulkFraudBatch $batch,
        BulkFraudBatchItem $item,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $this->owns($request, $portalContext, $batch);
        abort_unless($item->batch_id === $batch->id, 404);

        return response()->json($this->withQuota($request, $bulk->retry($request, $batch, $item)));
    }

    public function refresh(
        Request $request,
        BulkFraudBatch $batch,
        BulkFraudBatchItem $item,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCheck $bulk,
    ): JsonResponse {
        $this->owns($request, $portalContext, $batch);
        abort_unless($item->batch_id === $batch->id, 404);

        return response()->json($this->withQuota($request, $bulk->refresh($request, $batch, $item)));
    }

    public function preview(
        Request $request,
        BulkFraudBatch $batch,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCsv $csv,
    ): JsonResponse {
        $this->owns($request, $portalContext, $batch);

        return response()->json($csv->table($batch));
    }

    public function export(
        Request $request,
        BulkFraudBatch $batch,
        MerchantPortalContext $portalContext,
        MerchantBulkFraudCsv $csv,
    ): StreamedResponse {
        $this->owns($request, $portalContext, $batch);

        return $csv->download($batch);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withQuota(Request $request, array $data): array
    {
        $merchant = app(MerchantPortalContext::class)->resolveMerchant($request->user());
        $data['quota'] = app(CourierHistoryQuota::class)->snapshot($merchant);

        return $data;
    }

    private function owns(Request $request, MerchantPortalContext $portalContext, BulkFraudBatch $batch): void
    {
        $merchant = $portalContext->resolveMerchant($request->user());
        abort_unless((int) $batch->user_id === (int) $merchant->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MerchantBulkFraudCheck $bulk, BulkFraudBatch $batch): array
    {
        $batch->loadMissing('items');

        return [
            'batch' => $bulk->summary($batch),
            'items' => $batch->items->map->toPortalRow()->values(),
        ];
    }
}
