<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkFraudBatch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'status',
        'submitted_count',
        'unique_count',
        'duplicates_removed',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BulkFraudBatchItem::class, 'batch_id')->orderBy('position');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
