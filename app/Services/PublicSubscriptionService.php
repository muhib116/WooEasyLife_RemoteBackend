<?php

namespace App\Services;

use App\Mail\SubscriptionInquiryAdminMail;
use App\Models\PackageHub;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PublicSubscriptionService
{
    public const SESSION_PENDING_INQUIRY_KEY = 'pending_subscription_inquiry_id';

    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected PackagePaymentService $packagePaymentService,
        protected PackagePlanResolver $planResolver,
        protected DomainAvailabilityService $domainAvailability,
        protected LandingSettingsService $landingSettings,
    ) {
    }

    /**
     * Soft-save contact-step lead without requiring DNS or payment.
     * Drafts never block a later full purchase (not in OPEN_STATUSES).
     *
     * @param  array<string, mixed>  $data
     * @return array{inquiry: SubscriptionInquiry, created: bool}
     */
    public function saveLead(?User $user, array $data): array
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

        // If they already have an open purchase request, keep that — do not clone a draft.
        $open = $this->findOpenDuplicate($user, $domain, $data);
        if ($open) {
            return [
                'inquiry' => $open,
                'created' => false,
            ];
        }

        $dnsOk = array_key_exists('dns_verified', $data)
            ? (bool) $data['dns_verified']
            : $this->domainNormalizer->resolvesPublicly($domain);

        $orderLimit = (int) ($data['order_limit'] ?? $packageHub->order_rate_token ?? 0);
        $totalAmount = isset($data['total_amount'])
            ? round((float) $data['total_amount'], 2)
            : round((float) ($packageHub->package_price ?? 0), 2);

        if ($this->planResolver->isCatalog($packageHub)) {
            $orderLimit = max(1, (int) ($packageHub->order_rate_token ?? $orderLimit));
            $totalAmount = round((float) ($packageHub->package_price ?? 0), 2);
        }

        $note = $this->buildStructuredNote($data, $domain);
        if (! $dnsOk) {
            $note .= "\nLead note: DNS A record not verified yet (captured before live domain check).";
        }

        $draft = $this->findDraftForContact($user, $data);

        $payload = [
            'user_id' => $user?->id ?? $draft?->user_id,
            'package_hub_id' => $packageHub->id,
            'domain' => $domain,
            'customer_name' => $data['customer_name'] ?? null,
            'email' => $data['email'],
            'contact_number' => $this->normalizePhone($data['contact_number'] ?? null) ?? $data['contact_number'],
            'whatsapp_number' => $this->normalizePhone($data['whatsapp_number'] ?? null) ?? $data['whatsapp_number'],
            'address' => filled($data['address'] ?? null) ? $data['address'] : ($draft?->address ?: ''),
            'order_limit' => $orderLimit,
            'total_amount' => $totalAmount,
            'transaction_charge' => 0,
            'transaction_method' => null,
            'transaction_id' => null,
            'account_number' => null,
            'note' => $note,
            'status' => SubscriptionInquiry::STATUS_DRAFT,
            'source' => 'landing_pricing_lead',
        ];

        if ($draft) {
            $draft->fill($payload);
            $draft->save();

            return [
                'inquiry' => $draft->fresh()->load('packageHub:id,title'),
                'created' => false,
            ];
        }

        $inquiry = SubscriptionInquiry::create($payload);
        $this->notifyAdmin($inquiry->load('packageHub:id,title'));

        return [
            'inquiry' => $inquiry,
            'created' => true,
        ];
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

        $this->assertDomainAvailableForPublicSubscribe($user, $domain);

        $duplicate = $this->findOpenDuplicate($user, $domain, $data);
        if ($duplicate) {
            throw ValidationException::withMessages([
                'subscription' => $this->duplicateMessage($duplicate),
                'website_url' => $this->duplicateMessage($duplicate),
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
        $draft = $this->findDraftForContact($user, $data);

        $result = DB::transaction(function () use (
            $user,
            $data,
            $domain,
            $packageHub,
            $orderLimit,
            $totalAmount,
            $note,
            $canUsePortalPayment,
            $draft,
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

            $payload = [
                'user_id' => $user?->id ?? $draft?->user_id,
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
                'status' => SubscriptionInquiry::STATUS_PENDING,
                'source' => 'landing_pricing',
                'package_payment_request_id' => $paymentRequestId,
            ];

            if ($draft) {
                $draft->fill($payload);
                $draft->save();
                $inquiry = $draft->fresh();
            } else {
                $inquiry = SubscriptionInquiry::create($payload);
            }

            return [
                'inquiry' => $inquiry->load('packageHub:id,title'),
                'payment_request_id' => $paymentRequestId,
            ];
        });

        $this->notifyAdmin($result['inquiry']);

        return $result;
    }

    protected function notifyAdmin(SubscriptionInquiry $inquiry): void
    {
        $adminEmail = $this->landingSettings->adminEmail();

        if (! filled($adminEmail)) {
            Log::warning('Subscription inquiry admin email skipped: no admin email configured.', [
                'inquiry_id' => $inquiry->id,
            ]);

            return;
        }

        try {
            Mail::to($adminEmail)->send(new SubscriptionInquiryAdminMail($inquiry));
        } catch (\Throwable $e) {
            Log::error('Failed to send subscription inquiry admin email.', [
                'inquiry_id' => $inquiry->id,
                'admin_email' => $adminEmail,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Pending/in-progress inquiry for the current visitor (session and/or auth).
     *
     * @return array<string, mixed>|null
     */
    public function resolvePendingForVisitor(?User $user, ?int $sessionInquiryId = null): ?array
    {
        $inquiry = null;

        if ($sessionInquiryId) {
            $inquiry = SubscriptionInquiry::query()
                ->open()
                ->whereKey($sessionInquiryId)
                ->with('packageHub:id,title')
                ->first();
        }

        if (! $inquiry && $user) {
            $inquiry = SubscriptionInquiry::query()
                ->open()
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);

                    if (filled($user->email)) {
                        $query->orWhereRaw('LOWER(email) = ?', [strtolower((string) $user->email)]);
                    }
                })
                ->with('packageHub:id,title')
                ->latest('id')
                ->first();
        }

        return $inquiry ? $this->serializePending($inquiry) : null;
    }

    /**
     * Soft validation for realtime UI (does not throw).
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, errors: array<string, string>, normalized: array<string, string|null>}
     */
    public function validateRealtime(?User $user, array $data): array
    {
        $errors = [];
        $normalized = [
            'website_url' => null,
            'email' => null,
            'contact_number' => null,
            'whatsapp_number' => null,
        ];

        $rawDomain = trim((string) ($data['website_url'] ?? $data['domain'] ?? ''));
        if ($rawDomain !== '') {
            $domain = $this->domainNormalizer->normalize($rawDomain);
            if (! $domain) {
                $errors['website_url'] = 'সঠিক ডোমেইন নাম লিখুন (যেমন: myshop.com)।';
            } else {
                $normalized['website_url'] = $domain;

                try {
                    $this->assertDomainAvailableForPublicSubscribe($user, $domain);
                } catch (ValidationException $e) {
                    $errors['website_url'] = $e->errors()['website_url'][0]
                        ?? 'এই ডোমেইন ব্যবহার করা যাবে না।';
                }

                if (! isset($errors['website_url'])) {
                    $duplicate = $this->findOpenDuplicate($user, $domain, $data);
                    if ($duplicate) {
                        $errors['website_url'] = $this->duplicateMessage($duplicate);
                        $errors['subscription'] = $this->duplicateMessage($duplicate);
                    }
                }
            }
        }

        $rawEmail = trim((string) ($data['email'] ?? ''));
        if ($rawEmail !== '') {
            if (! filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'সঠিক ইমেইল ঠিকানা লিখুন (যেমন: name@example.com)।';
            } else {
                $normalized['email'] = strtolower($rawEmail);
            }
        }

        foreach (['contact_number' => 'মোবাইল নম্বর', 'whatsapp_number' => 'WhatsApp নম্বর'] as $field => $label) {
            $rawPhone = trim((string) ($data[$field] ?? ''));
            if ($rawPhone === '') {
                continue;
            }

            $phone = $this->normalizePhone($rawPhone);
            if (! $phone || ! preg_match('/^01[3-9]\d{8}$/', $phone)) {
                $errors[$field] = "সঠিক বাংলাদেশি {$label} লিখুন (যেমন: 017XXXXXXXX)।";
            } else {
                $normalized[$field] = $phone;
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'normalized' => $normalized,
        ];
    }

    /**
     * Reject domains already registered to another merchant
     * (websites, packages, license tokens, businesses, SMS balances).
     */
    public function assertDomainAvailableForPublicSubscribe(?User $user, string $domain): void
    {
        $normalized = $this->domainAvailability->normalize($domain);
        if (! $normalized) {
            throw ValidationException::withMessages([
                'website_url' => 'সঠিক ওয়েবসাইট URL বা ডোমেইন লিখুন (যেমন: myshop.com)।',
            ]);
        }

        if (! $this->domainNormalizer->resolvesPublicly($normalized)) {
            throw ValidationException::withMessages([
                'website_url' => 'ডোমেইনের DNS A রেকর্ড পাওয়া যায়নি। লাইভ ওয়েবসাইটের সঠিক ডোমেইন দিন।',
            ]);
        }

        if (! $this->domainAvailability->isEnforcementEnabled()) {
            return;
        }

        $ownerId = $this->domainAvailability->findOwnerUserId($normalized);

        if ($ownerId === null) {
            return;
        }

        if ($user && (int) $user->id === (int) $ownerId) {
            return;
        }

        $message = 'এই ডোমেইন ইতিমধ্যে WooEasyLife-এ অন্য মার্চেন্টের অধীনে নিবন্ধিত। প্রয়োজনে সাপোর্টে যোগাযোগ করুন।';

        throw ValidationException::withMessages([
            'website_url' => $message,
            'subscription' => $message,
        ]);
    }

    /**
     * Duplicate = same domain AND (same email OR same phone) in open inquiries.
     *
     * @param  array<string, mixed>  $data
     */
    public function findOpenDuplicate(?User $user, string $domain, array $data): ?SubscriptionInquiry
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phones = array_values(array_unique(array_filter([
            $this->normalizePhone($data['contact_number'] ?? null),
            $this->normalizePhone($data['whatsapp_number'] ?? null),
        ])));

        if ($email === '' && $phones === []) {
            return null;
        }

        $candidates = SubscriptionInquiry::query()
            ->open()
            ->where('domain', $domain)
            ->with('packageHub:id,title')
            ->latest('id')
            ->limit(50)
            ->get();

        return $candidates->first(function (SubscriptionInquiry $inquiry) use ($email, $phones) {
            if ($email !== '' && strtolower((string) $inquiry->email) === $email) {
                return true;
            }

            if ($phones === []) {
                return false;
            }

            $storedPhones = array_filter([
                $this->normalizePhone($inquiry->contact_number),
                $this->normalizePhone($inquiry->whatsapp_number),
            ]);

            return count(array_intersect($phones, $storedPhones)) > 0;
        });
    }

    /**
     * Latest unfinished lead for the same visitor (email / phone), any domain.
     *
     * @param  array<string, mixed>  $data
     */
    public function findDraftForContact(?User $user, array $data): ?SubscriptionInquiry
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phones = array_values(array_unique(array_filter([
            $this->normalizePhone($data['contact_number'] ?? null),
            $this->normalizePhone($data['whatsapp_number'] ?? null),
        ])));

        if ($email === '' && $phones === [] && ! $user) {
            return null;
        }

        return SubscriptionInquiry::query()
            ->where('status', SubscriptionInquiry::STATUS_DRAFT)
            ->where(function ($query) use ($email, $phones, $user) {
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                foreach ($phones as $phone) {
                    $query->orWhere('contact_number', $phone)
                        ->orWhere('whatsapp_number', $phone);
                }

                if ($user) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->with('packageHub:id,title')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePending(SubscriptionInquiry $inquiry): array
    {
        $statusLabel = match ($inquiry->status) {
            SubscriptionInquiry::STATUS_CONTACTED => 'যোগাযোগ চলছে',
            default => 'যাচাইয়ের অপেক্ষায়',
        };

        return [
            'id' => $inquiry->id,
            'status' => $inquiry->status,
            'status_label' => $statusLabel,
            'domain' => $inquiry->domain,
            'plan_title' => $inquiry->packageHub?->title,
            'email' => $inquiry->email,
            'created_at' => optional($inquiry->created_at)?->toIso8601String(),
            'message' => $this->duplicateMessage($inquiry),
        ];
    }

    public function duplicateMessage(SubscriptionInquiry $inquiry): string
    {
        $plan = $inquiry->packageHub?->title;
        $domain = $inquiry->domain;

        if ($plan && $domain) {
            return "আপনার «{$plan}» অনুরোধ ({$domain}) এখনও প্রক্রিয়াধীন। অনুমোদন না হওয়া পর্যন্ত নতুন সাবস্ক্রিপশন অনুরোধ করা যাবে না।";
        }

        if ($domain) {
            return "আপনার {$domain} ডোমেইনের সাবস্ক্রিপশন অনুরোধ এখনও প্রক্রিয়াধীন। অনুমোদন না হওয়া পর্যন্ত আবার অনুরোধ করা যাবে না।";
        }

        return 'আপনার একটি সাবস্ক্রিপশন অনুরোধ এখনও প্রক্রিয়াধীন। অনুমোদন না হওয়া পর্যন্ত নতুন অনুরোধ করা যাবে না।';
    }

    public function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '88') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits !== '' ? $digits : null;
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
