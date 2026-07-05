<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicSubscriptionService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected PackagePaymentService $packagePaymentService,
        protected PackagePlanResolver $planResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{inquiry: SubscriptionInquiry, payment_request_id: int|null}
     */
    public function submit(?User $user, array $data, bool $canUsePortalPayment = false): array
    {
        $domain = $this->domainNormalizer->normalize($data['website_url'] ?? $data['domain'] ?? null);
        if (! $domain) {
            throw ValidationException::withMessages([
                'website_url' => 'সঠিক ওয়েবসাইট URL বা ডোমেইন লিখুন (যেমন: myshop.com)।',
            ]);
        }

        $packageHub = PackageHub::query()
            ->where('id', $data['package_hub_id'] ?? null)
            ->where('is_active', true)
            ->whereNotNull('package_duration')
            ->first();

        if (! $packageHub) {
            throw ValidationException::withMessages([
                'package_hub_id' => 'নির্বাচিত প্যাকেজটি এখন উপলব্ধ নেই।',
            ]);
        }

        $orderLimit = (int) ($data['order_limit'] ?? $packageHub->order_rate_token ?? 0);
        $totalAmount = isset($data['total_amount'])
            ? round((float) $data['total_amount'], 2)
            : round((float) ($packageHub->package_price ?? 0), 2);

        if ($this->planResolver->isCatalog($packageHub)) {
            $orderLimit = max(1, (int) ($packageHub->order_rate_token ?? $orderLimit));
            $totalAmount = round((float) ($packageHub->package_price ?? 0), 2);
        }

        $note = $this->buildStructuredNote($data, $domain);

        return DB::transaction(function () use (
            $user,
            $data,
            $domain,
            $packageHub,
            $orderLimit,
            $totalAmount,
            $note,
            $canUsePortalPayment,
        ) {
            $paymentRequestId = null;

            if ($canUsePortalPayment && $user) {
                $paymentRequest = $this->packagePaymentService->createRequest($user, [
                    'domain' => $domain,
                    'package_hub_id' => $packageHub->id,
                    'order_limit' => $orderLimit,
                    'total_amount' => $totalAmount,
                    'transaction_method' => $data['transaction_method'] ?? null,
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'account_number' => $data['account_number'] ?? null,
                    'transaction_charge' => $data['transaction_charge'] ?? 0,
                    'note' => $note,
                ]);

                $paymentRequestId = $paymentRequest->id;
            }

            $inquiry = SubscriptionInquiry::create([
                'user_id' => $user?->id,
                'package_hub_id' => $packageHub->id,
                'domain' => $domain,
                'customer_name' => $data['customer_name'] ?? null,
                'email' => $data['email'],
                'contact_number' => $data['contact_number'],
                'whatsapp_number' => $data['whatsapp_number'],
                'address' => $data['address'],
                'order_limit' => $orderLimit,
                'total_amount' => $totalAmount,
                'transaction_charge' => round((float) ($data['transaction_charge'] ?? 0), 2),
                'transaction_method' => $data['transaction_method'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'note' => $note,
                'status' => 'pending',
                'source' => 'landing_pricing',
                'package_payment_request_id' => $paymentRequestId,
            ]);

            return [
                'inquiry' => $inquiry->load('packageHub:id,title'),
                'payment_request_id' => $paymentRequestId,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildStructuredNote(array $data, string $domain): string
    {
        $lines = [
            'Landing subscription inquiry',
            'Website: '.$domain,
            'Email: '.($data['email'] ?? ''),
            'Contact: '.($data['contact_number'] ?? ''),
            'WhatsApp: '.($data['whatsapp_number'] ?? ''),
            'Address: '.($data['address'] ?? ''),
        ];

        if (! empty($data['customer_name'])) {
            $lines[] = 'Name: '.$data['customer_name'];
        }

        if (! empty($data['note'])) {
            $lines[] = 'Customer note: '.$data['note'];
        }

        return implode("\n", $lines);
    }
}
