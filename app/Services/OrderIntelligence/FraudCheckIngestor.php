<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Jobs\OrderIntelligence\ProjectCustomerStatsJob;
use App\Models\AccessToken;
use App\Models\OrderIntelligence\PlatformCustomer;
use Illuminate\Support\Facades\Log;

class FraudCheckIngestor
{
    public function __construct(
        private CustomerResolver $customerResolver,
        private OrderTracker $orderTracker,
        private StatusTransitionService $statusTransitionService,
        private CourierIntelPersister $courierIntelPersister,
        private IntelligenceCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $steadfastReport
     * @param  array<string, mixed>  $pathaoReport
     * @param  array<string, mixed>  $paperflyReport
     */
    public function ingest(
        FraudCheckOrderContext $context,
        array $steadfastReport,
        array $pathaoReport,
        array $paperflyReport,
    ): void {
        if (! config('order_intelligence.enabled', true)) {
            return;
        }

        try {
            $customer = $this->resolveCustomer($context);

            if ($context->canIngest()) {
                $this->persistOrderContext($customer, $context);
            }

            $this->courierIntelPersister->persistFromFraudCheckReports(
                customer: $customer,
                phoneNormalized: $customer->phone_normalized,
                steadfastReport: $steadfastReport,
                pathaoReport: $pathaoReport,
                paperflyReport: $paperflyReport,
                sourceAccessTokenId: $context->accessTokenId,
            );

            $this->cache->forget($customer->phone_normalized, $context->accessTokenId);

            ProjectCustomerStatsJob::dispatch($customer->id);
        } catch (\Throwable $exception) {
            Log::warning('Order intelligence fraud check ingest failed.', [
                'phone' => $context->phone,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function ingestOrderContext(FraudCheckOrderContext $context): void
    {
        if (! config('order_intelligence.enabled', true)) {
            return;
        }

        try {
            $customer = $this->resolveCustomer($context);

            if ($context->canIngest()) {
                $this->persistOrderContext($customer, $context);
                $this->cache->forget($customer->phone_normalized, $context->accessTokenId);
                ProjectCustomerStatsJob::dispatch($customer->id);
            }
        } catch (\Throwable $exception) {
            Log::warning('Order intelligence order context ingest failed.', [
                'phone' => $context->phone,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveCustomer(FraudCheckOrderContext $context): PlatformCustomer
    {
        return $this->customerResolver->resolve($context->phone, $context->name, $context->address);
    }

    private function persistOrderContext(PlatformCustomer $customer, FraudCheckOrderContext $context): void
    {
        $order = $this->orderTracker->upsertFromFraudCheck($customer, $context);

        if ($order->wasRecentlyCreated) {
            $this->statusTransitionService->recordInitial(
                order: $order,
                status: OrderStatus::NewOrder,
                source: 'fraud_check',
                idempotencyKey: sprintf(
                    'fraud_check:%d:%d:new_order',
                    (int) $context->accessTokenId,
                    (int) $context->wcOrderId,
                ),
                accessTokenId: $context->accessTokenId,
            );
        }
    }

    public function resolveContextFromToken(?AccessToken $accessToken, array $payload): FraudCheckOrderContext
    {
        $userId = null;

        if ($accessToken && $accessToken->tokenable_id) {
            $userId = (int) $accessToken->tokenable_id;
        }

        return FraudCheckOrderContext::fromRequestPayload(
            $payload,
            $accessToken?->id,
            $userId,
        );
    }
}
