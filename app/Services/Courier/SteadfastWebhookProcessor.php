<?php

namespace App\Services\Courier;

use App\Jobs\ProcessSteadfastWebhookForward;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;
use App\Services\OrderIntelligence\WebhookOrderIngestor;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SteadfastWebhookProcessor
{
    public const FORWARD_TIMEOUT_SECONDS = 8;

    public const QUEUED_CLAIM_AFTER_SECONDS = 20;

    public const FORWARDING_STUCK_AFTER_SECONDS = 120;

    public function __construct(
        protected CourierShipmentService $shipmentService,
        protected CourierWebhookEventService $eventService,
        protected WordPressCourierForwarder $forwarder,
        protected CourierForwardRetryService $retryService,
        protected WebhookOrderIngestor $webhookOrderIngestor,
    ) {}

    public function receive(Request $request, string $environment = 'live'): void
    {
        $payload = $request->all();
        $notificationType = strtolower((string) ($payload['notification_type'] ?? 'delivery_status'));
        $consignmentId = trim((string) ($payload['consignment_id'] ?? ''));
        $invoice = trim((string) ($payload['invoice'] ?? ''));

        if ($consignmentId === '') {
            return;
        }

        $updatedAt = (string) ($payload['updated_at'] ?? now()->toDateTimeString());

        if ($notificationType === 'tracking_update') {
            $this->enqueue($request, $environment, $consignmentId, $invoice, [
                'partner' => 'steadfast',
                'notification_type' => 'tracking_update',
                'tracking_message' => (string) ($payload['tracking_message'] ?? ''),
                'updated_at' => $updatedAt,
                'invoice' => $invoice,
            ], 'tracking_update', $updatedAt);

            return;
        }

        $rawStatus = $this->normalizeStatus((string) ($payload['status'] ?? ''));
        if ($rawStatus === '') {
            return;
        }

        $this->enqueue($request, $environment, $consignmentId, $invoice, [
            'partner' => 'steadfast',
            'notification_type' => 'delivery_status',
            'raw_status' => $rawStatus,
            'updated_at' => $updatedAt,
            'tracking_message' => (string) ($payload['tracking_message'] ?? ''),
            'invoice' => $invoice,
            'cod_amount' => $payload['cod_amount'] ?? null,
            'delivery_charge' => $payload['delivery_charge'] ?? null,
        ], $rawStatus, $updatedAt);
    }

    public function schedule(int $eventId): void
    {
        $queue = (string) config('queue.default', 'sync');

        if ($queue !== 'sync') {
            ProcessSteadfastWebhookForward::dispatch($eventId);

            return;
        }

        dispatch(function () use ($eventId) {
            app(self::class)->process($eventId);
        })->afterResponse();
    }

    public function process(int $eventId): void
    {
        $claimed = CourierWebhookEvent::query()
            ->where('id', $eventId)
            ->where('forward_status', 'queued')
            ->update([
                'forward_status' => 'forwarding',
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $event = CourierWebhookEvent::query()->find($eventId);
        if (! $event) {
            return;
        }

        $shipment = $event->shipment_id
            ? CourierShipment::query()->find($event->shipment_id)
            : null;

        if (! $shipment || ! $shipment->wc_order_id) {
            $event->forward_status = 'orphan';
            $event->forward_message = 'mapping_not_found';
            $event->save();

            return;
        }

        if ($this->hasNewerSuccess($event)) {
            $event->forward_status = 'success';
            $event->forward_message = 'stale_update_ignored';
            $event->save();

            return;
        }

        $payload = $event->payload_summary['forward_payload'] ?? null;
        if (! is_array($payload)) {
            $event->forward_status = 'failed';
            $event->forward_message = 'missing_forward_payload';
            $event->save();

            return;
        }

        unset($payload['consignment_id'], $payload['forward_payload']);

        try {
            $this->webhookOrderIngestor->ingest($shipment, $payload, $event);

            $result = $this->forwarder->forward($shipment, $payload, self::FORWARD_TIMEOUT_SECONDS);

            if (! empty($result['success'])) {
                $shipment->status = (string) ($payload['raw_status'] ?? $shipment->status);
                $shipment->last_webhook_at = now();
                $shipment->save();

                $event->forward_status = 'success';
                $event->forward_message = 'forwarded';
                $event->save();

                return;
            }

            $error = (string) ($result['message'] ?? 'forward_failed');
            $this->markRetry($event, $shipment, $payload, $error);
        } catch (Throwable $exception) {
            Log::error('Steadfast webhook forward failed', [
                'event_id' => $event->id,
                'message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            $this->markRetry($event, $shipment, $payload, $exception->getMessage());
        }
    }

    /**
     * @return array{reset: int, processed: int}
     */
    public function recoverStuck(int $limit = 25, ?int $stopAfterSeconds = null): array
    {
        $limit = max(1, $limit);
        $deadline = $stopAfterSeconds !== null ? microtime(true) + max(1, $stopAfterSeconds) : null;

        $reset = CourierWebhookEvent::query()
            ->where('partner', 'steadfast')
            ->where('forward_status', 'forwarding')
            ->where('updated_at', '<=', now()->subSeconds(self::FORWARDING_STUCK_AFTER_SECONDS))
            ->update([
                'forward_status' => 'queued',
                'updated_at' => now(),
            ]);

        $promotedIds = $this->promoteMappedOrphans($limit);

        $ids = CourierWebhookEvent::query()
            ->where('partner', 'steadfast')
            ->where('forward_status', 'queued')
            ->where('created_at', '<=', now()->subSeconds(self::QUEUED_CLAIM_AFTER_SECONDS))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $ids = collect($promotedIds)->merge($ids)->unique()->take($limit)->values();

        $processed = 0;
        foreach ($ids as $id) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            $this->process((int) $id);
            $processed++;
        }

        return [
            'reset' => (int) $reset,
            'processed' => $processed,
        ];
    }

    /**
     * @param  array<string, mixed>  $forwardBody
     */
    private function enqueue(
        Request $request,
        string $environment,
        string $consignmentId,
        string $invoice,
        array $forwardBody,
        string $eventType,
        string $updatedAt
    ): void {
        $idempotencyKey = $this->idempotencyKey($request);

        if ($idempotencyKey !== null && $this->respondToExisting($idempotencyKey)) {
            return;
        }

        $shipment = $this->shipmentService->resolveShipment(
            'steadfast',
            $consignmentId,
            $invoice,
            $environment
        );

        $forwardPayload = $forwardBody;
        $forwardStatus = 'orphan';
        $forwardMessage = 'mapping_not_found';

        if ($shipment && $shipment->wc_order_id) {
            $forwardPayload = array_merge($forwardBody, [
                'order_id' => (int) $shipment->wc_order_id,
                'courier_account_id' => (int) $shipment->courier_account_id,
            ]);
            $forwardStatus = 'queued';
            $forwardMessage = null;
        }

        try {
            $event = $this->eventService->record('steadfast', $environment, [
                'consignment_id' => $consignmentId,
                'shipment_id' => $shipment?->id,
                'access_token_id' => $shipment?->access_token_id,
                'site_url' => $shipment?->site_url,
                'wc_order_id' => $shipment?->wc_order_id,
                'event_type' => $eventType,
                'idempotency_key' => $idempotencyKey,
                'forward_status' => $forwardStatus,
                'forward_message' => $forwardMessage,
                'payload_summary' => [
                    'partner' => 'steadfast',
                    'consignment_id' => $consignmentId,
                    'updated_at' => $updatedAt,
                    'forward_payload' => $forwardPayload,
                ],
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $this->respondToExisting($idempotencyKey);

            return;
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $this->respondToExisting($idempotencyKey);

            return;
        }

        if ($forwardStatus === 'queued') {
            $this->schedule($event->id);
        }
    }

    private function respondToExisting(?string $idempotencyKey): bool
    {
        if ($idempotencyKey === null) {
            return false;
        }

        $existing = CourierWebhookEvent::query()
            ->where('partner', 'steadfast')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existing) {
            return false;
        }

        if ($existing->forward_status === 'failed') {
            $requeued = CourierWebhookEvent::query()
                ->where('id', $existing->id)
                ->where('forward_status', 'failed')
                ->update([
                    'forward_status' => 'queued',
                    'forward_message' => null,
                    'updated_at' => now(),
                ]);

            if ($requeued === 1) {
                $this->schedule($existing->id);
            }
        } elseif ($existing->forward_status === 'orphan' && $this->promoteOrphan($existing)) {
            $this->schedule($existing->id);
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private function promoteMappedOrphans(int $limit): array
    {
        $orphans = CourierWebhookEvent::query()
            ->where('partner', 'steadfast')
            ->where('forward_status', 'orphan')
            ->where('created_at', '>=', now()->subHours(6))
            ->whereNotNull('consignment_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $promoted = [];

        foreach ($orphans as $orphan) {
            if ($this->promoteOrphan($orphan)) {
                $promoted[] = (int) $orphan->id;
            }
        }

        return $promoted;
    }

    private function promoteOrphan(CourierWebhookEvent $event): bool
    {
        $summary = is_array($event->payload_summary) ? $event->payload_summary : [];
        $forward = is_array($summary['forward_payload'] ?? null) ? $summary['forward_payload'] : [];
        $invoice = trim((string) ($forward['invoice'] ?? ''));
        $shipment = $this->shipmentService->resolveShipment(
            'steadfast',
            (string) $event->consignment_id,
            $invoice,
            (string) $event->environment
        );

        if (! $shipment || ! $shipment->wc_order_id) {
            return false;
        }

        $forward['order_id'] = (int) $shipment->wc_order_id;
        $forward['courier_account_id'] = (int) $shipment->courier_account_id;
        unset($forward['consignment_id'], $forward['forward_payload']);
        $summary['forward_payload'] = $forward;

        return CourierWebhookEvent::query()
            ->where('id', $event->id)
            ->where('forward_status', 'orphan')
            ->update([
                'shipment_id' => $shipment->id,
                'access_token_id' => $shipment->access_token_id,
                'site_url' => $shipment->site_url,
                'wc_order_id' => $shipment->wc_order_id,
                'forward_status' => 'queued',
                'forward_message' => null,
                'payload_summary' => json_encode($summary),
                'updated_at' => now(),
            ]) === 1;
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            return null;
        }

        return mb_substr($key, 0, 191);
    }

    private function hasNewerSuccess(CourierWebhookEvent $event): bool
    {
        $mine = $this->timestampFromSummary($event);
        if ($mine === null || ! $event->shipment_id) {
            return false;
        }

        $others = CourierWebhookEvent::query()
            ->where('shipment_id', $event->shipment_id)
            ->where('id', '!=', $event->id)
            ->where('forward_status', 'success')
            ->get(['id', 'payload_summary']);

        foreach ($others as $other) {
            $theirs = $this->timestampFromSummary($other);
            if ($theirs !== null && $theirs > $mine) {
                return true;
            }
        }

        return false;
    }

    private function timestampFromSummary(CourierWebhookEvent $event): ?int
    {
        $raw = (string) ($event->payload_summary['updated_at'] ?? '');
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markRetry(CourierWebhookEvent $event, CourierShipment $shipment, array $payload, string $error): void
    {
        $event->forward_status = 'retry_queued';
        $event->forward_message = mb_substr($error, 0, 255);
        $event->save();

        $this->retryService->queueRetry($shipment, $payload, $event, $error);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000'
            || $driverCode === '1062'
            || str_contains($exception->getMessage(), 'UNIQUE')
            || str_contains($exception->getMessage(), 'Duplicate');
    }

    private function normalizeStatus(string $status): string
    {
        $raw = strtolower(trim($status));
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/[\s-]+/', '_', $raw) ?? $raw;

        $aliases = [
            'complete' => 'delivered',
            'completed' => 'delivered',
            'returned' => 'returned',
            'partial_delivery' => 'partial_delivered',
            'partially_delivered' => 'partial_delivered',
            'canceled' => 'cancelled',
        ];

        return $aliases[$raw] ?? $raw;
    }
}
