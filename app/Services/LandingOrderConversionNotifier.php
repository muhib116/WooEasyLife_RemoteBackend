<?php

namespace App\Services;

use App\LogHelper;
use App\Mail\LandingOrderConvertedMail;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class LandingOrderConversionNotifier
{
    public function __construct(
        private BulkSmsService $bulkSms,
    ) {}

    /**
     * @return array{email: bool, sms: bool, errors: array<int, string>}
     */
    public function notify(SubscriptionInquiry $inquiry, User $merchant, bool $userCreated): array
    {
        $result = [
            'email' => false,
            'sms' => false,
            'errors' => [],
        ];

        if ((bool) config('landing.conversion_notifications.email', true) && filled($merchant->email)) {
            try {
                Mail::to($merchant->email)->send(new LandingOrderConvertedMail($inquiry, $merchant, $userCreated));
                $result['email'] = true;
            } catch (\Throwable $th) {
                LogHelper::saveLog('landing convert email failed', $th->getMessage());
                $result['errors'][] = 'Email failed: '.$th->getMessage();
            }
        }

        $shouldSms = (bool) config('landing.conversion_notifications.sms', true)
            && $this->bulkSms->isConfigured()
            && filled($merchant->phone);

        if ($shouldSms) {
            $message = $this->smsMessage($inquiry, $merchant, $userCreated);
            $sms = $this->bulkSms->send((string) $merchant->phone, $message);

            if ($sms['ok']) {
                $result['sms'] = true;
            } else {
                $result['errors'][] = 'SMS failed: '.($sms['message'] ?? 'unknown');
            }
        }

        return $result;
    }

    private function smsMessage(SubscriptionInquiry $inquiry, User $merchant, bool $userCreated): string
    {
        $login = route('merchant.login');

        if ($userCreated) {
            return "WooEasyLife: আপনার অ্যাকাউন্ট রেডি। Login: {$merchant->email} / Password: আপনার মোবাইল নম্বর। প্রথম লগইনে পাসওয়ার্ড বদলান। {$login}";
        }

        return "WooEasyLife: আপনার স্টোর ({$inquiry->domain}) এর প্ল্যান অ্যাক্টিভ হয়েছে। Login: {$login}";
    }
}
