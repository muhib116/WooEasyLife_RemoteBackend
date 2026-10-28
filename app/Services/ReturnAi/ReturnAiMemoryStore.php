<?php

namespace App\Services\ReturnAi;

use App\Models\AccessToken;
use App\Models\ReturnAiMemory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Each store's memory rules ("which situation → what to do"), kept on the hub
 * and given to every review of that store. Phones are masked on save.
 */
class ReturnAiMemoryStore
{
    /**
     * @return array{max_items: int, when: int, then: int}
     */
    public static function limits(): array
    {
        return [
            'max_items' => (int) config('return_ai.max_memory_items', 30),
            'when' => (int) config('return_ai.max_memory_when_chars', 200),
            'then' => (int) config('return_ai.max_memory_then_chars', 400),
        ];
    }

    /**
     * @return list<array{id: int, when: string, then: string, active: bool, updated_at: string}>
     */
    public function all(AccessToken $token): array
    {
        return $this->query($token)->orderBy('id')->get()->map(fn (ReturnAiMemory $m) => $this->present($m))->all();
    }

    /**
     * Rules that are on, in the shape the prompt reads.
     *
     * @return list<array{id: string, when: string, then: string}>
     */
    public function forPrompt(AccessToken $token): array
    {
        return $this->query($token)->where('active', true)->orderBy('id')->get()
            ->map(fn (ReturnAiMemory $m) => [
                'id' => 'm'.$m->id,
                'when' => (string) $m->situation,
                'then' => (string) $m->instruction,
            ])->all();
    }

    /**
     * Changes when a rule that is on changes; empty when there are none.
     */
    public function hash(AccessToken $token): string
    {
        $rules = $this->forPrompt($token);

        return $rules === [] ? '' : sha1((string) json_encode($rules, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Create (no id) or update a rule. Errors are Bangla for the store's UI.
     *
     * @param  array{id?: int|null, when?: string|null, then?: string|null, active?: bool|null}  $input
     * @return array{ok: true, item: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function save(AccessToken $token, array $input): array
    {
        $limits = self::limits();
        $id = (int) ($input['id'] ?? 0);
        $memory = null;
        if ($id > 0) {
            $memory = $this->query($token)->whereKey($id)->first();
            if (! $memory) {
                return ['ok' => false, 'error' => 'এই মেমোরি পাওয়া যায়নি', 'status' => 404];
            }
        } elseif ($this->query($token)->count() >= $limits['max_items']) {
            return ['ok' => false, 'error' => "সর্বোচ্চ {$limits['max_items']}টি মেমোরি রাখা যায় — পুরোনো একটি মুছে নতুন যোগ করুন", 'status' => 422];
        }

        $when = array_key_exists('when', $input) ? $this->clean($input['when'], $limits['when']) : (string) ($memory?->situation ?? '');
        $then = array_key_exists('then', $input) ? $this->clean($input['then'], $limits['then']) : (string) ($memory?->instruction ?? '');
        if ($then === '') {
            return ['ok' => false, 'error' => '“কী করবে” লিখুন', 'status' => 422];
        }

        $memory ??= new ReturnAiMemory([
            'website_id' => $token->website_id,
            'personal_access_token_id' => $token->website_id ? null : $token->id,
            'user_id' => $token->tokenable_id,
        ]);
        $memory->situation = $when !== '' ? $when : null;
        $memory->instruction = $then;
        if (array_key_exists('active', $input) && $input['active'] !== null) {
            $memory->active = (bool) $input['active'];
        } elseif (! $memory->exists) {
            $memory->active = true;
        }
        $memory->save();

        return ['ok' => true, 'item' => $this->present($memory)];
    }

    public function delete(AccessToken $token, int $id): bool
    {
        return $this->query($token)->whereKey($id)->delete() > 0;
    }

    /**
     * @return Builder<ReturnAiMemory>
     */
    private function query(AccessToken $token): Builder
    {
        return $token->website_id
            ? ReturnAiMemory::query()->where('website_id', $token->website_id)
            : ReturnAiMemory::query()->whereNull('website_id')->where('personal_access_token_id', $token->id);
    }

    private function clean(mixed $text, int $max): string
    {
        $text = (string) preg_replace('/[ \t]+/u', ' ', strip_tags((string) $text));
        $text = (string) preg_replace('/\s*\n\s*/u', "\n", trim($text));

        return mb_substr(ReturnAiText::maskPhones($text), 0, $max);
    }

    /**
     * @return array{id: int, when: string, then: string, active: bool, created_at: string, updated_at: string}
     */
    private function present(ReturnAiMemory $memory): array
    {
        $local = static fn ($at): string => (string) $at?->copy()->timezone('Asia/Dhaka')->format('Y-m-d H:i:s');

        return [
            'id' => (int) $memory->id,
            'when' => (string) $memory->situation,
            'then' => (string) $memory->instruction,
            'active' => (bool) $memory->active,
            'created_at' => $local($memory->created_at),
            'updated_at' => $local($memory->updated_at),
        ];
    }
}
