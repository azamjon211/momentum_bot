<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentProcessor;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Facades\App\Contracts\Publisher;

class TransactionController extends Controller
{
    public function show(int $transactionId, TransactionService $transactionService, PaymentProcessor $paymentProcessor){
        $transaction = $transactionService->findTransaction($transactionId);
        $paymentProcessor->process($transaction);
        return 'Transaction :' . $transactionId  . ', ' . $transaction['amount'];
    }
}
