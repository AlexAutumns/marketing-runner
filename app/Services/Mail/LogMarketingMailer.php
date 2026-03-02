<?php

namespace App\Services\Mail;

use App\Contracts\MarketingMailer;
use Illuminate\Support\Facades\Log;

class LogMarketingMailer implements MarketingMailer
{
    public function send(array $message): array
    {
        // This is your “email send” for demo: write everything into laravel.log.
        Log::info('[MarketingMailer:log] Email send simulated', [
            'to' => $message['to'],
            'subject' => $message['subject'],
            'trackingId' => $message['trackingId'] ?? null,
            'html_preview' => mb_substr(strip_tags($message['html']), 0, 200),
        ]);

        return [
            'provider' => 'log',
            'status' => 'sent',
            'providerMessageId' => null,
            'error' => null,
        ];
    }
}
