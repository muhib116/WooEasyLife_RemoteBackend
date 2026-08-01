<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WiseApiKey extends Model
{
    protected $table = 'wise_api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'status',
        'turns_count',
        'last_used_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function turns(): HasMany
    {
        return $this->hasMany(WiseTurn::class, 'wise_api_key_id');
    }

    /**
     * Create a key and return it with the plain secret (shown once).
     *
     * @return array{key: self, plain: string}
     */
    public static function generate(string $name): array
    {
        $plain = 'wise_' . Str::random(40);

        $key = self::create([
            'name' => $name,
            'key_hash' => hash('sha256', $plain),
            'key_prefix' => substr($plain, 0, 13),
            'status' => 'active',
        ]);

        return ['key' => $key, 'plain' => $plain];
    }

    public static function findActiveByPlainKey(string $plain): ?self
    {
        $plain = trim($plain);

        if ($plain === '') {
            return null;
        }

        return self::query()
            ->where('key_hash', hash('sha256', $plain))
            ->where('status', 'active')
            ->first();
    }

    public function recordUsage(): void
    {
        $this->newQuery()
            ->whereKey($this->getKey())
            ->update([
                'turns_count' => $this->getConnection()->raw('turns_count + 1'),
                'last_used_at' => now(),
            ]);
    }
}
