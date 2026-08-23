<?php

namespace App\Services;

use App\Contracts\PaymentProcessor;

class Stripe implements PaymentProcessor
{
    public function __construct(private array $config)
    {
    }

    public function process(array $transaction):void{
        echo 'Processing Stripe payment' . $transaction['transactionId'] . '<br/>';
    }
}
