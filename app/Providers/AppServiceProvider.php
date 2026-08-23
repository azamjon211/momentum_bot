<?php

namespace App\Providers;

use App\Contracts\PaymentProcessor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
//        $this->app->bind(PaymentProcessor::class,Stripe::class);
//        $this->app->when(ClickPaymentController::class)
//            ->needs(PaymentProcessor::class)
//            ->give(ClickPaymentProcess::class);
//        $this->app->when(StripePaymentController::class)
//            ->needs(PaymentProcessor::class)
//            ->give(Stripe::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
