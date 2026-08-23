<?php

namespace App\Facades;

use App\Contracts\PaymentProcessor;
use Illuminate\Support\Facades\Facade;

class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentProcessor::class;
//        return 'paymentProcessor';
    }
}
