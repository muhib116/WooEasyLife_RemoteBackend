<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandingOrderConversionService
{
    public const ACQUISITION_PREFIX = 'landing_order:';

    public function __construct(
        private DomainNormalizer $domainNormalizer,
        private DomainAvailabilityService $domainAvailability,
        private PackagePaymentService $packagePaymentService,
        private LicenseProvisioningService $licenseProvisioning,
        private WebsiteSyncService $websiteSync,
        private PackagePlanResolver $planResolver,
        private LandingOrderConversionNotifier $notifier,
    ) {}

    /**
     * Pre-flight checks shown in the admin convert dialog.
     *
     * @return array<string, mixed>
     */
    public function preview(SubscriptionInquiry $inquiry): array
    {
        $inquiry->loadMissing(['packageHub', 'packagePaymentRequest', 'user']);

        $blockers = [];
        $warnings = [];

        if ($inquiry->status === SubscriptionInquiry::STATUS_CONVERTED) {
            $blockers[] = 'This landing order is already converted.';
        }

        if ($inquiry->status === SubscriptionInquiry::STATUS_REJECTED) {
            $blockers[] = 'Rejected orders cannot be converted. Change the status first if this was a mistake.';
        }

        $package = $inquiry->packageHub;
        if (! $package instanceof PackageHub || ! $package->is_active) {
            $blockers[] = 'The selected plan is missing or inactive.';
        }

        $domain = $this->domainNormalizer->normalize($inquiry->domain);
        if (! $domain) {
            $blockers[] = 'Invalid website domain on this order.';
        }

        $email = strtolower(trim((string) $inquiry->email));
        $phone = trim((string) $inquiry->contact_number);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $blockers[] = 'A valid customer email is required.';
        }

        if ($phone === '') {
            $blockers[] = 'A contact phone number is required (used as the initial password).';
        }

        $userResolution = [
            'action' => 'create',
            'user_id' => null,
            'email' => $email,
            'label' => 'Create new merchant',
        ];

        try {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                [$resolvedUser, $created] = $this->resolveUserForPreview($inquiry, $email);
                $userResolution = [
                    'action' => $created ? 'create' : 'reuse',
                    'user_id' => $resolvedUser?->id,
                    'email' => $email,
                    'label' => $created
                        ? 'Create new merchant'
                        : 'Reuse existing merchant #'.($resolvedUser?->id ?? '—'),
                ];

                if (! $created && $resolvedUser) {
                    $warnings[] = 'Existing merchant will be reused. Password will not be changed.';
                }
            }
        } catch (ValidationException $e) {
            $blockers = array_merge($blockers, collect($e->errors())->flatten()->all());
        }

        $dnsOk = false;
        $domainOwnerId = null;

        if ($domain) {
            $dnsOk = $this->domainHasDns($domain);
            if (! $dnsOk) {
                $blockers[] = 'Domain has no DNS A record. Fix the website domain before converting.';
            }

            $domainOwnerId = $this->domainAvailability->findOwnerUserId($domain);
            $resolvedId = $userResolution['user_id'] ?? null;

            if ($domainOwnerId && $resolvedId && (int) $domainOwnerId !== (int) $resolvedId) {
                $blockers[] = "Domain is already registered to merchant #{$domainOwnerId}.";
            } elseif ($domainOwnerId && ! $resolvedId) {
                $blockers[] = "Domain is already registered to merchant #{$domainOwnerId}.";
            }
        }

        $paymentResolution = $this->previewPaymentResolution($inquiry, $userResolution['user_id'] ?? null);
        if ($paymentResolution['warning'] ?? null) {
            $warnings[] = $paymentResolution['warning'];
        }

        if ($package instanceof PackageHub && $this->planResolver->isFreeTrial($package) && $domain) {
            $warnings[] = 'This is a free-trial plan. Conversion will fail if the domain already used a free trial.';
        }

        return [
            'ok' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'domain' => $domain,
            'dns_ok' => $dnsOk,
            'domain_owner_id' => $domainOwnerId,
            'user_resolution' => $userResolution,
            'payment_resolution' => $paymentResolution,
            'credentials' => [
                'username' => $email,
                'password_hint' => $phone !== '' ? $phone : null,
                'password_applies' => ($userResolution['action'] ?? null) === 'create',
                'must_change_password' => ($userResolution['action'] ?? null) === 'create',
            ],
            'acquisition_source' => self::ACQUISITION_PREFIX.$inquiry->id,
        ];
    }

    /**
     * @return array{
     *     user: User,
     *     user_created: bool,
     *     user_package: UserPackage,
     *     payment_request: PackagePaymentRequest,
     *     plain_text_token: string,
     *     access_token: AccessToken,
     *     inquiry: SubscriptionInquiry
     * }
     */
    public function convert(SubscriptionInquiry $inquiry): array
    {
        $inquiry->loadMissing(['packageHub', 'packagePaymentRequest', 'user']);

        if ($inquiry->status === SubscriptionInquiry::STATUS_CONVERTED) {
            throw ValidationException::withMessages([
                'order' => 'This landing order is already converted to a merchant.',
            ]);
        }

        if ($inquiry->status === SubscriptionInquiry::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'order' => 'Rejected orders cannot be converted. Change the status first if this was a mistake.',
            ]);
        }

        $package = $inquiry->packageHub;

        if (! $package instanceof PackageHub || ! $package->is_active) {
            throw ValidationException::withMessages([
                'package_hub_id' => 'The selected plan is missing or inactive.',
            ]);
        }

        $domain = $this->domainNormalizer->normalize($inquiry->domain);

        if (! $domain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid website domain on this order.',
            ]);
        }

        if (! $this->domainHasDns($domain)) {
            throw ValidationException::withMessages([
                'domain' => 'Domain has no DNS A record. Fix the website domain before converting.',
            ]);
        }

        $email = strtolower(trim((string) $inquiry->email));
        $phone = trim((string) $inquiry->contact_number);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'A valid customer email is required to create the merchant login.',
            ]);
        }

        if ($phone === '') {
            throw ValidationException::withMessages([
                'contact_number' => 'A contact phone number is required (used as the initial password).',
            ]);
        }

        $result = DB::transaction(function () use ($inquiry, $package, $domain, $email, $phone) {
            [$user, $userCreated] = $this->resolveOrCreateUser($inquiry, $email, $phone);

            $this->domainAvailability->assertAvailableForUser($user, $domain, forAdmin: true);

            $this->websiteSync->resolveForUser(
                $user,
                $domain,
                $inquiry->customer_name ?: $user->name,
            );

            $paymentRequest = $this->resolvePaymentRequest($inquiry, $user, $package, $domain);
            $userPackage = $this->ensureApprovedPackage($paymentRequest);

            $license = $this->ensureLicense($user, $domain, $userPackage);

            $meta = [
                'converted_by' => Auth::id(),
                'converted_at' => now()->toIso8601String(),
                'user_id' => $user->id,
                'user_created' => $userCreated,
                'payment_request_id' => $paymentRequest->id,
                'user_package_id' => $userPackage->id,
                'access_token_id' => $license['access_token']->id,
                'domain' => $domain,
                'events' => [
                    [
                        'type' => 'converted',
                        'at' => now()->toIso8601String(),
                        'by' => Auth::id(),
                        'message' => $userCreated
                            ? 'Merchant created and provisioned from landing order.'
                            : 'Existing merchant provisioned from landing order.',
                    ],
                ],
                'notifications' => null,
            ];

            $inquiry->forceFill([
                'user_id' => $user->id,
                'package_payment_request_id' => $paymentRequest->id,
                'converted_access_token_id' => $license['access_token']->id,
                'status' => SubscriptionInquiry::STATUS_CONVERTED,
                'converted_at' => now(),
                'conversion_meta' => $meta,
                'note' => $this->appendConversionNote($inquiry->note, $user->id, $paymentRequest->id),
            ])->save();

            return [
                'user' => $user->fresh(),
                'user_created' => $userCreated,
                'user_package' => $userPackage,
                'payment_request' => $paymentRequest->fresh(),
                'plain_text_token' => $license['plain_text_token'],
                'access_token' => $license['access_token'],
                'inquiry' => $inquiry->fresh(['packageHub', 'user']),
            ];
        });

        $notify = $this->notifier->notify($result['inquiry'], $result['user'], $result['user_created']);

        $inquiry = $result['inquiry']->fresh();
        $meta = $inquiry->conversion_meta ?? [];
        $meta['notifications'] = [
            'email' => $notify['email'],
            'sms' => $notify['sms'],
            'errors' => $notify['errors'],
            'at' => now()->toIso8601String(),
        ];
        $meta['events'] = array_values(array_merge($meta['events'] ?? [], [[
            'type' => 'notified',
            'at' => now()->toIso8601String(),
            'message' => sprintf(
                'Notify: email=%s sms=%s',
                $notify['email'] ? 'yes' : 'no',
                $notify['sms'] ? 'yes' : 'no',
            ),
        ]]));
        $inquiry->forceFill(['conversion_meta' => $meta])->save();

        $result['inquiry'] = $inquiry->fresh(['packageHub', 'user']);
        $result['notifications'] = $notify;

        return $result;
    }

    /**
     * @return array{0: User, 1: bool}
     */
    private function resolveOrCreateUser(SubscriptionInquiry $inquiry, string $email, string $phone): array
    {
        $trashed = User::onlyTrashed()->where('email', $email)->first();

        if ($trashed) {
            throw ValidationException::withMessages([
                'email' => 'A deleted merchant already uses this email (user #'.$trashed->id.'). Restore that account from trash before converting.',
            ]);
        }

        $emailOwner = User::query()->where('email', $email)->first();

        if ($inquiry->user_id) {
            $linked = User::query()->find($inquiry->user_id);

            if ($linked && $linked->role === 'user') {
                if ($emailOwner && (int) $emailOwner->id !== (int) $linked->id) {
                    throw ValidationException::withMessages([
                        'email' => 'Order is linked to merchant #'.$linked->id.' but email belongs to merchant #'.$emailOwner->id.'. Resolve the mismatch before converting.',
                    ]);
                }

                $this->ensureAcquisitionSource($linked, $inquiry->id);

                return [$linked, false];
            }
        }

        if ($emailOwner) {
            if ($emailOwner->role !== 'user') {
                throw ValidationException::withMessages([
                    'email' => 'This email belongs to a non-merchant account and cannot be used for conversion.',
                ]);
            }

            $this->ensureAcquisitionSource($emailOwner, $inquiry->id);

            return [$emailOwner, false];
        }

        $user = User::create([
            'name' => trim((string) ($inquiry->customer_name ?: 'Merchant')),
            'email' => $email,
            'phone' => $phone,
            'whatsapp_phone' => trim((string) ($inquiry->whatsapp_number ?: $phone)),
            'address' => $inquiry->address,
            'password' => $phone,
            'must_change_password' => true,
            'role' => 'user',
            'status' => true,
            'acquisition_source' => self::ACQUISITION_PREFIX.$inquiry->id,
        ]);

        return [$user, true];
    }

    /**
     * @return array{0: ?User, 1: bool}
     */
    private function resolveUserForPreview(SubscriptionInquiry $inquiry, string $email): array
    {
        $trashed = User::onlyTrashed()->where('email', $email)->first();

        if ($trashed) {
            throw ValidationException::withMessages([
                'email' => 'A deleted merchant already uses this email (user #'.$trashed->id.'). Restore that account from trash before converting.',
            ]);
        }

        $emailOwner = User::query()->where('email', $email)->first();

        if ($inquiry->user_id) {
            $linked = User::query()->find($inquiry->user_id);

            if ($linked && $linked->role === 'user') {
                if ($emailOwner && (int) $emailOwner->id !== (int) $linked->id) {
                    throw ValidationException::withMessages([
                        'email' => 'Order is linked to merchant #'.$linked->id.' but email belongs to merchant #'.$emailOwner->id.'.',
                    ]);
                }

                return [$linked, false];
            }
        }

        if ($emailOwner) {
            if ($emailOwner->role !== 'user') {
                throw ValidationException::withMessages([
                    'email' => 'This email belongs to a non-merchant account.',
                ]);
            }

            return [$emailOwner, false];
        }

        return [null, true];
    }

    private function ensureAcquisitionSource(User $user, int $inquiryId): void
    {
        if (filled($user->acquisition_source)) {
            return;
        }

        $user->forceFill([
            'acquisition_source' => self::ACQUISITION_PREFIX.$inquiryId,
        ])->save();
    }

    /**
     * @return array{action: string, payment_request_id: int|null, warning: string|null}
     */
    private function previewPaymentResolution(SubscriptionInquiry $inquiry, ?int $userId): array
    {
        if ($inquiry->package_payment_request_id) {
            $existing = PackagePaymentRequest::query()->find($inquiry->package_payment_request_id);

            if ($existing && $userId && (int) $existing->user_id === (int) $userId) {
                return [
                    'action' => $existing->status === 'approved' ? 'reuse_approved' : 'approve_existing',
                    'payment_request_id' => $existing->id,
                    'warning' => null,
                ];
            }

            if ($existing && $userId && (int) $existing->user_id !== (int) $userId) {
                return [
                    'action' => 'create_new',
                    'payment_request_id' => null,
                    'warning' => 'Linked payment #'.$existing->id.' belongs to merchant #'.$existing->user_id.'. A new billing record will be created for the resolved merchant; the old payment is left unchanged.',
                ];
            }
        }

        return [
            'action' => 'create_new',
            'payment_request_id' => null,
            'warning' => null,
        ];
    }

    private function resolvePaymentRequest(
        SubscriptionInquiry $inquiry,
        User $user,
        PackageHub $package,
        string $domain
    ): PackagePaymentRequest {
        if ($inquiry->package_payment_request_id) {
            $existing = PackagePaymentRequest::query()->find($inquiry->package_payment_request_id);

            if ($existing && (int) $existing->user_id === (int) $user->id) {
                return $existing;
            }
            // Orphan / mismatched payment: leave it alone and create a correct billing row.
        }

        $pending = PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('domain', $domain)
            ->where('package_hub_id', $package->id)
            ->latest('id')
            ->first();

        if ($pending) {
            return $pending;
        }

        $note = trim(implode("\n", array_filter([
            'Converted from Landing Order #'.$inquiry->id,
            $inquiry->note,
        ])));

        return $this->packagePaymentService->createRequest($user, [
            'package_hub_id' => $package->id,
            'domain' => $domain,
            'order_limit' => $inquiry->order_limit,
            'total_amount' => $inquiry->total_amount,
            'transaction_charge' => $inquiry->transaction_charge,
            'transaction_method' => $inquiry->transaction_method ?: 'Bkash',
            'transaction_id' => $inquiry->transaction_id,
            'account_number' => $inquiry->account_number,
            'note' => $note,
            'intent' => 'subscribe',
        ]);
    }

    private function ensureApprovedPackage(PackagePaymentRequest $paymentRequest): UserPackage
    {
        if ($paymentRequest->status === 'approved' && $paymentRequest->user_package_id) {
            $userPackage = UserPackage::query()->find($paymentRequest->user_package_id);

            if ($userPackage) {
                return $userPackage;
            }
        }

        if ($paymentRequest->status === 'pending') {
            return $this->packagePaymentService->approve($paymentRequest);
        }

        throw ValidationException::withMessages([
            'payment' => 'Linked payment request cannot be approved (status: '.$paymentRequest->status.').',
        ]);
    }

    /**
     * @return array{access_token: AccessToken, plain_text_token: string}
     */
    private function ensureLicense(User $user, string $domain, UserPackage $userPackage): array
    {
        $existing = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('user_package_id', $userPackage->id)
            ->where('status', true)
            ->get()
            ->first(fn (AccessToken $token) => $this->domainNormalizer->normalize($token->domain) === $domain);

        if ($existing && filled($existing->access_key)) {
            try {
                return [
                    'access_token' => $existing,
                    'plain_text_token' => Crypt::decryptString($existing->access_key),
                ];
            } catch (\Throwable) {
                // Fall through and issue a fresh license.
            }
        }

        return $this->licenseProvisioning->create(
            $user,
            $domain,
            [
                'user_package_id' => $userPackage->id,
                'title' => $user->name.' — '.$domain,
                'description' => 'Auto-issued from landing order conversion',
                'status' => true,
            ],
            requireUserPackage: true,
            requireDns: ! app()->environment('testing'),
        );
    }

    private function domainHasDns(string $domain): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if ($this->domainNormalizer->hasDnsARecord($domain)) {
            return true;
        }

        if (str_starts_with($domain, 'www.')) {
            return $this->domainNormalizer->hasDnsARecord(substr($domain, 4));
        }

        return $this->domainNormalizer->hasDnsARecord('www.'.$domain);
    }

    private function appendConversionNote(?string $existing, int $userId, int $paymentRequestId): string
    {
        $line = sprintf(
            '[Converted by admin #%s at %s → user #%d, payment #%d]',
            Auth::id() ?? 'system',
            now()->toDateTimeString(),
            $userId,
            $paymentRequestId,
        );

        $existing = trim((string) $existing);

        return $existing === '' ? $line : $existing."\n".$line;
    }
}
