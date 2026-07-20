<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorialVideo extends Model
{
    protected $fillable = [
        'tutorial_category_id',
        'title',
        'path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TutorialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TutorialCategory::class, 'tutorial_category_id');
    }
}
