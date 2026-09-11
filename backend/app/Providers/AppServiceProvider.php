<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\WhatsAppGateway;
use App\Services\Gateways\LogPaymentGateway;
use App\Services\Gateways\LogWhatsAppGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Stub drivers for now; swap for Midtrans/Xendit + Cloud API/Fonnte in v2.
        $this->app->bind(PaymentGateway::class, LogPaymentGateway::class);
        $this->app->bind(WhatsAppGateway::class, LogWhatsAppGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
