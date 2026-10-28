<?php

namespace App\Services\ReturnAi;

/**
 * Reason codes and suspicion flags the return desk uses, with the Bangla the
 * store shows. Each flag lists the automatic actions still allowed while it is
 * on; any flag puts the case on the human-review shortlist and none allows cancel.
 */
final class ReturnAiCatalog
{
    /**
     * @return array<string, array{label_bn: string, decide: string}>
     */
    public static function reasons(): array
    {
        return [
            'unreachable' => ['label_bn' => 'ফোনে পাওয়া যাচ্ছে না', 'decide' => 'request_resend'],
            'wrong_address' => ['label_bn' => 'ঠিকানা ভুল / অসম্পূর্ণ', 'decide' => 'request_resend'],
            'changed_mind' => ['label_bn' => 'মত পরিবর্তন', 'decide' => 'confirm_cancel'],
            'reschedule' => ['label_bn' => 'পরে নিতে চায়', 'decide' => 'request_resend'],
            'price_dispute' => ['label_bn' => 'দাম নিয়ে আপত্তি', 'decide' => 'confirm_cancel'],
            'payment_issue' => ['label_bn' => 'পেমেন্ট সমস্যা', 'decide' => 'confirm_cancel'],
            'product_issue' => ['label_bn' => 'প্রোডাক্ট সমস্যা', 'decide' => 'confirm_cancel'],
            'rider_issue' => ['label_bn' => 'রাইডার/ডেলিভারি সমস্যা', 'decide' => 'request_resend'],
            'other' => ['label_bn' => 'অন্যান্য', 'decide' => ''],
            'unknown' => ['label_bn' => 'অস্পষ্ট', 'decide' => ''],
        ];
    }

    /**
     * @return list<string>
     */
    public static function reasonCodes(): array
    {
        return array_keys(self::reasons());
    }

    public static function reasonLabel(string $code): string
    {
        return self::reasons()[$code]['label_bn'] ?? self::reasons()['unknown']['label_bn'];
    }

    /**
     * @return array<string, array{label_bn: string, auto: list<string>}>
     */
    public static function flags(): array
    {
        $keepSale = ['note', 'request_resend'];

        return [
            'unreachable_claim_unverified' => ['label_bn' => 'রাইডার বলছে কাস্টমার ফোন ধরে না — আপনার কল লগে যাচাই নেই', 'auto' => $keepSale],
            'merchant_reached_customer' => ['label_bn' => 'রাইডার বলছে ফোন ধরে না, অথচ আপনার কল লগে কাস্টমারের সাথে কথা হয়েছে', 'auto' => []],
            'good_history_contradicts_refusal' => ['label_bn' => 'কাস্টমারের ডেলিভারি রেকর্ড ভালো, তবু রাইডার বলছে নেবে না / ফোন ধরে না', 'auto' => $keepSale],
            'cod_short_payment_claim' => ['label_bn' => 'রাইডার টাকা কম / আংশিক পেমেন্টের কথা বলছে — টাকার বিষয় নিজে দেখুন', 'auto' => []],
            'partial_delivery_claim' => ['label_bn' => 'আংশিক ডেলিভারির দাবি — নিজে যাচাই করুন', 'auto' => []],
            'contradicting_notes' => ['label_bn' => 'নোটগুলো একটার সাথে আরেকটা মিলছে না', 'auto' => []],
            'template_note_repeated' => ['label_bn' => 'একই নোট বারবার — আসল চেষ্টা হয়েছে কিনা সন্দেহ', 'auto' => $keepSale],
            'no_attempt_evidence' => ['label_bn' => 'আসল ডেলিভারি চেষ্টার কোনো চিহ্ন নেই', 'auto' => $keepSale],
            'address_claim_unverified' => ['label_bn' => 'রাইডার ঠিকানা খুঁজে পায়নি বলছে — যাচাই হয়নি', 'auto' => $keepSale],
            'time_pattern_odd' => ['label_bn' => 'নোটের সময় অস্বাভাবিক', 'auto' => $keepSale],
            'promise_date_passed' => ['label_bn' => 'কাস্টমার যে দিনে নেওয়ার কথা বলেছিল তা পার হয়ে গেছে', 'auto' => $keepSale],
            'already_delivered' => ['label_bn' => 'কুরিয়ারে পার্সেল ডেলিভারড / আংশিক ডেলিভারড দেখাচ্ছে', 'auto' => []],
            'evidence_unverified' => ['label_bn' => 'এআই-এর দেওয়া প্রমাণ নোটে হুবহু মেলেনি', 'auto' => []],
            'verifier_disagreed' => ['label_bn' => 'দ্বিতীয় যাচাইয়ে এআই একমত হয়নি', 'auto' => []],
            'low_confidence' => ['label_bn' => 'এআই যথেষ্ট নিশ্চিত নয়', 'auto' => []],
            'no_information' => ['label_bn' => 'সিদ্ধান্ত নেওয়ার মতো তথ্য নেই', 'auto' => []],
            'ai_unavailable' => ['label_bn' => 'এআই চলেনি — শুধু নিয়ম দিয়ে সাজেশন', 'auto' => []],
            'other' => ['label_bn' => 'অন্য কোনো সন্দেহ', 'auto' => []],
        ];
    }

    /**
     * Flags the model may raise about rider notes.
     *
     * @return list<string>
     */
    public static function modelFlagCodes(): array
    {
        return [
            'unreachable_claim_unverified',
            'merchant_reached_customer',
            'good_history_contradicts_refusal',
            'cod_short_payment_claim',
            'partial_delivery_claim',
            'contradicting_notes',
            'template_note_repeated',
            'no_attempt_evidence',
            'address_claim_unverified',
            'time_pattern_odd',
            'other',
        ];
    }

    /**
     * @return array{code: string, label_bn: string, detail_bn: string, source: string}
     */
    public static function flag(string $code, string $source = 'rules', string $detail = ''): array
    {
        $flags = self::flags();
        $code = isset($flags[$code]) ? $code : 'other';

        return [
            'code' => $code,
            'label_bn' => $flags[$code]['label_bn'],
            'detail_bn' => $detail,
            'source' => $source,
        ];
    }

    /**
     * Courier statuses that mean the parcel already reached the customer.
     *
     * @return list<string>
     */
    public static function deliveredStatuses(): array
    {
        return ['delivered', 'partial_delivered', 'delivered_approval_pending', 'partial_delivered_approval_pending'];
    }
}
