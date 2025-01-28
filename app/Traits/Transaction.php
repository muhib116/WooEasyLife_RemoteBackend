<?php

namespace App\Traits;

use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Auth;

trait Transaction
{
    /**
     * Create a transaction record.
     *
     * @param int|string $id The ID of the related model (transactional ID).
     * @param string $model The related model's class name (transactional type).
     * @param float|int $amount The transaction amount (default is 0).
     * @param string $type The transaction type. Must be one of: 'in' (credit) or 'out' (debit). Default is 'in'.
     * @return \Illuminate\Database\Eloquent\Model The created transaction instance.
     */
    public function createTransaction($id, $model, $amount = 0, $type = 'in')
    {
        // 'updated_by' => '',
        $data = [
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'transactional_id' => $id,
            'transactional_type' => $model,
            'amount' => $amount,
            'type' => $type,
        ];

        $transaction = TransactionHistory::create($data);

        return $transaction;
    }

    public function insertTransaction($data)
    {
        $transaction = TransactionHistory::insert($data);
        return $transaction;
    }

    public function updateTransaction($id, $data)
    {
        $transaction = TransactionHistory::find($id);
        if ($transaction) {
            $transaction->update($data);
            return true;
        }
        return false;
    }
}
