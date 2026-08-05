<?php

namespace App\WiseAi\Voice;

/**
 * Spoken decision side-channel for future voice adapters.
 * No telephony / STT / TTS — only structured speak + next_action.
 */
class VoiceContractBuilder
{
    public const MAX_SPEAK_CHARS = 160;

    public const HANDOFF_SPEAK = 'একটু অপেক্ষা করুন—মার্চেন্টের সাথে কানেক্ট করছি।';

    /**
     * @param  array<string, mixed>  $context
     */
    public function wantsVoiceProfile(string $channel, array $context): bool
    {
        if (strtolower(trim($channel)) === 'voice') {
            return true;
        }

        $profile = strtolower(trim((string) ($context['output_profile'] ?? '')));

        return $profile === 'voice';
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function attach(array $decision, string $channel, array $context): array
    {
        if (! $this->wantsVoiceProfile($channel, $context)) {
            return $decision;
        }

        $decision['voice'] = $this->build($decision);

        return $decision;
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array{
     *     speak_text: string,
     *     next_action: string,
     *     slot_to_ask: ?string,
     *     max_speak_chars: int,
     *     gap: bool
     * }
     */
    public function build(array $decision): array
    {
        $gap = (bool) ($decision['gap'] ?? false);
        $action = (string) ($decision['action'] ?? 'needs_human');
        $intent = (string) ($decision['intent'] ?? 'unknown');
        $missing = $decision['missing_context'] ?? null;
        $missing = is_string($missing) ? $missing : null;

        [$nextAction, $slot] = $this->mapNextAction($action, $intent, $gap, $missing);

        $reply = trim((string) ($decision['suggested_reply'] ?? ''));
        $speak = $this->shorten($reply, self::MAX_SPEAK_CHARS);

        if ($speak === '' && $nextAction === 'transfer_human') {
            $speak = self::HANDOFF_SPEAK;
        }

        return [
            'speak_text' => $speak,
            'next_action' => $nextAction,
            'slot_to_ask' => $slot,
            'max_speak_chars' => self::MAX_SPEAK_CHARS,
            'gap' => $gap,
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function mapNextAction(string $action, string $intent, bool $gap, ?string $missing): array
    {
        if ($gap || $action === 'needs_human') {
            return ['transfer_human', null];
        }

        if ($action === 'clarify') {
            $slot = $this->slotForClarify($intent, $missing);

            return ['ask_slot', $slot];
        }

        if ($action === 'suggest_reply') {
            if (in_array($intent, ['thanks', 'ack'], true)) {
                return ['end', null];
            }

            return ['continue', null];
        }

        return ['transfer_human', null];
    }

    private function slotForClarify(string $intent, ?string $missing): ?string
    {
        if ($missing === 'offer') {
            return 'product';
        }

        return match ($intent) {
            'price', 'stock' => 'product',
            'delivery', 'cod' => 'area',
            'order_status' => 'order_id',
            'payment' => 'payment_method',
            default => null,
        };
    }

    private function shorten(string $text, int $max): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(?<=[।.!?])\s+/u', $text) ?: [$text];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $candidate = $out === '' ? $part : $out.' '.$part;
            if (mb_strlen($candidate) > $max) {
                break;
            }
            $out = $candidate;
        }

        if ($out === '') {
            $out = trim((string) ($parts[0] ?? $text));
        }

        if (mb_strlen($out) > $max) {
            $out = rtrim(mb_substr($out, 0, max(1, $max - 1))).'…';
        }

        return $out;
    }
}
