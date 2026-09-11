<?php

namespace App\Contracts;

/**
 * WhatsApp gateway seam. Concrete driver (official Cloud API OR Fonnte/Wablas)
 * chosen in v2. Real sends should go through a queued job with retry (PLANNING §6).
 */
interface WhatsAppGateway
{
    /** Send a message to a phone number. Return a provider message id / reference. */
    public function send(string $toPhone, string $message, array $options = []): string;
}
