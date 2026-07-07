<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Models\OrderIntelligence\MerchantOrderDetail;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformOrder;

class OrderTracker
{
    public function upsertFromFraudCheck(
        PlatformCustomer $customer,
        FraudCheckOrderContext $context,
    ): PlatformOrder {
        $now = now();

        $existing = PlatformOrder::query()
            ->where('access_token_id', (int) $context->accessTokenId)
            ->where('wc_order_id', (int) $context->wcOrderId)
            ->first();

        $attributes = [
            'platform_customer_id' => $customer->id,
            'user_id' => (int) $context->userId,
            'external_ref' => $context->externalRef,
            'order_amount' => $context->orderAmount,
            'currency' => $context->currency ?? 'BDT',
            'source' => 'fraud_check',
            'fraud_checked_at' => $now,
        ];

        if (! $existing) {
            $attributes['current_status'] = OrderStatus::NewOrder->value;
            $attributes['status_changed_at'] = $now;
        }

        $order = PlatformOrder::query()->updateOrCreate(
            [
                'access_token_id' => (int) $context->accessTokenId,
                'wc_order_id' => (int) $context->wcOrderId,
            ],
            $attributes,
        );

        MerchantOrderDetail::query()->updateOrCreate(
            ['platform_order_id' => $order->id],
            [
                'access_token_id' => (int) $context->accessTokenId,
                'customer_name' => $context->name,
                'customer_address' => $context->address,
                'product_title' => $context->productTitle,
                'product_sku' => $context->productSku,
                'quantity' => max(1, (int) ($context->quantity ?? 1)),
            ],
        );

        $customer->last_order_at = $now;
        $customer->save();

        return $order->fresh(['details']);
    }
}
