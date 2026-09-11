<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Placeholder driver until a real gateway (Midtrans/Xendit) is wired in v2. */
class LogPaymentGateway implements PaymentGateway
{
    public function createCharge(array $payload): array
    {
        Log::info('LogPaymentGateway::createCharge', $payload);

        return ['reference' => 'stub_' . Str::uuid(), 'status' => 'pending', 'driver' => 'log'];
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        // No real signature to verify yet. Never treat the stub as verified in production.
        return false;
    }
}
