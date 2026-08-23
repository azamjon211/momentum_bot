<?php

namespace App\Services;

class TransactionService
{
    public function findTransaction(int $transactionId){
        return [
            'transactionId' => $transactionId,
            'amount' => 25,
        ];
    }
}
