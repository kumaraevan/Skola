<?php

namespace App\Contracts;

/**
 * Payment gateway seam. Concrete driver (Midtrans OR Xendit) chosen in v2.
 * The webhook handler MUST verify the signature and be idempotent (PLANNING §5.2).
 */
interface PaymentGateway
{
    /** Create a charge (VA/QRIS/retail) and return gateway reference + instructions. */
    public function createCharge(array $payload): array;

    /** Verify an incoming webhook's authenticity. Return true only if the signature checks out. */
    public function verifyWebhook(array $headers, string $rawBody): bool;
}
