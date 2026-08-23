<?php

namespace App\Providers;

use App\Contracts\PaymentProcessor;
use Illuminate\Foundation\Application;
use App\Services\Stripe;
use Illuminate\Support\ServiceProvider;

class PaymentProcessorProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProcessor::class, function (Application $app) {
            return $app->make(Stripe::class, ['config' => []]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function provides(): array
    {
        return [

            'paymentProcessor',        ];
    }
}
