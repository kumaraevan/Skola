<?php

namespace App\Services\Gateways;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Placeholder driver until a real gateway (Cloud API / Fonnte / Wablas) is wired in v2. */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $toPhone, string $message, array $options = []): string
    {
        Log::info('LogWhatsAppGateway::send', ['to' => $toPhone, 'message' => $message]);

        return 'stub_' . Str::uuid();
    }
}
