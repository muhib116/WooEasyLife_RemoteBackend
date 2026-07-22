<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCluster extends Model
{
    protected $fillable = [
        'key',
        'label',
        'seed_queries',
        'landing_json',
        'detect_needles',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'seed_queries' => 'array',
            'landing_json' => 'array',
            'detect_needles' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
