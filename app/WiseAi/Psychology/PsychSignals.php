<?php

namespace App\WiseAi\Psychology;

/**
 * Customer + business psychology tags (Wave C3).
 * Signals / Assist priority / style hints only — never invents facts or changes knowledge answers.
 */
class PsychSignals
{
    public const VERSION = '1.0';

    /**
     * @param  array<string, mixed>  $language  LanguageNormalizer output
     * @param  array<string, mixed>  $classified  DecideEngine::classify result
     * @param  array<string, mixed>  $decision  In-progress decision (intent/action/gap/missing_context)
     * @return array{
     *     version: string,
     *     emotion: string,
     *     emotions: list<string>,
     *     journey: string,
     *     priority: string,
     *     style_hint: string,
     *     signals: list<array{code: string, source: string}>,
     *     side_channel: true
     * }
     */
    public function tag(string $rawText, array $language, array $classified, array $decision): array
    {
        $text = mb_strtolower(trim($rawText.' '.($language['canonical'] ?? '')));
        $signals = [];
        $emotions = [];

        $add = function (string $emotion, string $code, string $source) use (&$emotions, &$signals): void {
            if (! in_array($emotion, $emotions, true)) {
                $emotions[] = $emotion;
            }
            $signals[] = ['code' => $code, 'source' => $source];
        };

        foreach ($language['emoji_signals'] ?? [] as $emoji) {
            if (! is_array($emoji)) {
                continue;
            }
            $polarity = (string) ($emoji['polarity'] ?? '');
            $signal = (string) ($emoji['signal'] ?? '');
            if ($polarity === 'neg' || in_array($signal, ['angry', 'sad'], true)) {
                $add('angry', 'emoji_'.$signal, 'emoji');
            } elseif ($polarity === 'pos' || in_array($signal, ['happy', 'love'], true)) {
                $add('happy', 'emoji_'.$signal, 'emoji');
            }
        }

        $patterns = [
            'angry' => ['রাগ', 'খারাপ', 'বাজে', 'ঠক', 'scam', 'fraud', 'cheat', 'useless', 'worst'],
            'urgent' => ['এখনই', 'জলদি', 'তাড়াতাড়ি', 'আজকেই', 'urgent', 'asap', 'right now', 'today only'],
            'hesitant' => ['একটু ভাবি', 'ভাবি', 'নিশ্চিত না', 'maybe', 'not sure', 'ভাবছি', 'পরে'],
            'confused' => ['বুঝলাম না', 'বুঝি না', 'কি বলতে', 'confused', 'what do you mean'],
            'curious' => ['জানতে চাই', 'details', 'আরো বলেন', 'tell me more', 'কী কী আছে'],
            'happy' => ['ধন্যবাদ', 'শুক্রীয়া', 'thanks', 'thank you', 'ভালো', 'great', 'perfect'],
            'price_sensitive' => ['কমাবেন', 'কম দাম', 'ডিসকাউন্ট', 'discount', 'offer', 'কত কম', 'cheap'],
        ];

        foreach ($patterns as $emotion => $needles) {
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($text, mb_strtolower($needle))) {
                    $add($emotion, 'lex_'.$emotion, 'text');
                    break;
                }
            }
        }

        if ($emotions === []) {
            $kind = (string) ($classified['kind'] ?? '');
            $intent = (string) ($classified['intent'] ?? $decision['intent'] ?? 'unknown');
            if ($kind === 'social' || in_array($intent, ['greeting', 'thanks', 'bye', 'ack'], true)) {
                $emotions[] = 'happy';
                $signals[] = ['code' => 'social_default', 'source' => 'intent'];
            } elseif (($decision['action'] ?? '') === 'clarify' || $intent === 'unknown') {
                $emotions[] = 'confused';
                $signals[] = ['code' => 'clarify_default', 'source' => 'action'];
            } else {
                $emotions[] = 'curious';
                $signals[] = ['code' => 'neutral_curious', 'source' => 'default'];
            }
        }

        $primary = $this->primaryEmotion($emotions);
        $journey = $this->journey($classified, $decision);
        $priority = $this->priority($emotions, $decision);
        $style = $this->styleHint($primary, $priority);

        return [
            'version' => self::VERSION,
            'emotion' => $primary,
            'emotions' => array_values($emotions),
            'journey' => $journey,
            'priority' => $priority,
            'style_hint' => $style,
            'signals' => $signals,
            'side_channel' => true,
        ];
    }

    /**
     * @param  list<string>  $emotions
     */
    private function primaryEmotion(array $emotions): string
    {
        $rank = ['angry', 'urgent', 'hesitant', 'confused', 'price_sensitive', 'curious', 'happy'];
        foreach ($rank as $e) {
            if (in_array($e, $emotions, true)) {
                return $e;
            }
        }

        return $emotions[0] ?? 'curious';
    }

    /**
     * @param  array<string, mixed>  $classified
     * @param  array<string, mixed>  $decision
     */
    private function journey(array $classified, array $decision): string
    {
        $intent = (string) ($decision['intent'] ?? $classified['intent'] ?? 'unknown');
        $action = (string) ($decision['action'] ?? '');

        if (in_array($intent, ['greeting', 'thanks', 'bye', 'ack'], true)) {
            return 'awareness';
        }
        if ($intent === 'unknown' || $action === 'clarify') {
            return 'interest';
        }
        if (($decision['gap'] ?? false) || $action === 'needs_human') {
            return 'decision';
        }

        return match ($intent) {
            'price', 'availability', 'compare', 'bargain' => 'decision',
            'delivery', 'payment', 'return', 'warranty' => 'trust',
            'order_status' => 'purchase',
            default => 'interest',
        };
    }

    /**
     * @param  list<string>  $emotions
     * @param  array<string, mixed>  $decision
     */
    private function priority(array $emotions, array $decision): string
    {
        if (in_array('angry', $emotions, true) && in_array('urgent', $emotions, true)) {
            return 'critical';
        }
        if (in_array('angry', $emotions, true) || in_array('urgent', $emotions, true)) {
            return 'high';
        }
        if (($decision['gap'] ?? false) || ($decision['action'] ?? '') === 'needs_human') {
            return 'high';
        }

        return 'normal';
    }

    private function styleHint(string $emotion, string $priority): string
    {
        if ($priority === 'critical' || $emotion === 'angry') {
            return 'calm_reassure';
        }

        return match ($emotion) {
            'urgent' => 'clear_and_fast',
            'hesitant' => 'patient_no_pressure',
            'confused' => 'simple_steps',
            'price_sensitive' => 'value_transparent',
            'happy' => 'warm_brief',
            default => 'neutral_clear',
        };
    }
}
