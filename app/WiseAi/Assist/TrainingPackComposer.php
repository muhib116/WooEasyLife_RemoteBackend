<?php

namespace App\WiseAi\Assist;

/**
 * Wave 4 — compose micro prompt slices (never one mega system prompt).
 */
final class TrainingPackComposer
{
    /**
     * @param  array<string, mixed>  $pack  Context pack
     * @return array{
     *     knowledge: string,
     *     personality: string,
     *     conversation_style: string,
     *     decision_rules: string,
     *     prompt_version: string
     * }
     */
    public function compose(array $pack): array
    {
        $chunks = [];
        foreach ($pack['evidence_pack'] ?? [] as $i => $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            $chunks[] = sprintf(
                '[%d] id=%s type=%s title=%s :: %s',
                $i + 1,
                (string) ($chunk['id'] ?? ''),
                (string) ($chunk['type'] ?? ''),
                (string) ($chunk['title'] ?? ''),
                mb_substr((string) ($chunk['answer'] ?? ''), 0, 280),
            );
        }

        $tools = [];
        foreach ($pack['tool_facts'] ?? [] as $fact) {
            if (! is_array($fact)) {
                continue;
            }
            $tools[] = ($fact['source'] ?? 'tool').'.'.($fact['key'] ?? '').'='.($fact['value'] ?? '');
        }

        return [
            'knowledge' => $chunks === []
                ? '(no published evidence chunks)'
                : implode("\n", $chunks)
                    .($tools !== [] ? "\nTOOLS:\n".implode("\n", $tools) : ''),
            'personality' => 'Friendly professional BD commerce assistant. Warm, never robotic, never sound like an AI.',
            'conversation_style' => implode("\n", [
                'Do not repeat yourself.',
                'Do not lecture or over-explain.',
                'Do not use markdown.',
                'Do not say Certainly.',
                'Mirror customer language and emotion.',
                'Short natural replies; follow-up questions when needed.',
            ]),
            'decision_rules' => implode("\n", is_array($pack['rules_slice'] ?? null) ? $pack['rules_slice'] : [
                'If product unknown → ask.',
                'If policy missing → admit unknown.',
                'If customer angry → apologize first.',
            ]),
            'prompt_version' => (string) config(
                'wise_ai.grounded_assist.prompt_version',
                GroundedAssistSchema::PROMPT_VERSION,
            ),
        ];
    }
}
