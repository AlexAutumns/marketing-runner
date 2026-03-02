<?php

namespace App\Services\Mail;

use App\Contracts\MarketingMailer;
use Illuminate\Support\Facades\Mail;

class LaravelSmtpMarketingMailer implements MarketingMailer
{
    public function send(array $message): array
    {
        // Uses Laravel's configured mail driver (smtp, mailgun, etc.)
        Mail::html($message['html'], function ($m) use ($message) {
            $m->to($message['to'])
                ->subject($message['subject']);
        });

        return [
            'provider' => config('mail.default', 'smtp'),
            'status' => 'sent',
            'providerMessageId' => null,
            'error' => null,
        ];
    }
}
