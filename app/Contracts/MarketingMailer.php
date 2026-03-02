<?php

namespace App\Contracts;

interface MarketingMailer
{
    /**
     * Send an email and return provider info.
     *
     * @param array{
     *   to: string,
     *   subject: string,
     *   html: string,
     *   trackingId?: string|null
     * } $message
     * @return array{provider: string, status: string, providerMessageId?: string|null, error?: string|null}
     */
    public function send(array $message): array;
}
