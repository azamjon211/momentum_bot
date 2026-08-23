<?php

namespace App\Contracts;

interface PaymentProcessor
{
    public function process(array $transaction):void;
}
