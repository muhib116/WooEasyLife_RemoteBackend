<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageUseHistory extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'use_details' => 'json',
    ];

    public function transactionHistory() {
        return $this->morphOne(TransactionHistory::class, 'transactional');
    }
}
