<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsBalance extends Model
{
    protected $guarded = ['id'];

    public function transactionHistory() {
        return $this->morphOne(TransactionHistory::class, 'transactional');
    }
}
