<?php

namespace App\Services\Storage;

use App\Contracts\MarketingStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoSqliteMarketingStorage implements MarketingStorage
{
    public function listContacts(): Collection
    {
        return DB::table('contacts')->get();
    }

    public function getLatestActivity(string $contactId): ?object
    {
        return DB::table('contact_activities')
            ->where('contact_id', $contactId)
            ->orderByDesc('last_messaging_date')
            ->first();
    }

    public function hasComplied(string $contactId, ?string $trackingId, ?string $lastSendTime): bool
    {
        return DB::table('contact_engagements')
            ->where('contact_id', $contactId)
            ->where('engagement_status', 'YES')
            ->when($trackingId, fn ($q) => $q->where('tracking_id', $trackingId))
            ->when(! $trackingId && $lastSendTime, fn ($q) => $q->where('occurred_at', '>=', $lastSendTime))
            ->exists();
    }

    public function insertActivity(array $activityRow): void
    {
        DB::table('contact_activities')->insert($activityRow);
    }

    public function findContactByEmail(string $email): ?object
    {
        return DB::table('contacts')->where('personal_email', $email)->first();
    }

    public function insertEngagement(array $engagementRow): void
    {
        DB::table('contact_engagements')->insert($engagementRow);
    }

    public function updateContactOnComply(string $contactId, array $fields): void
    {
        DB::table('contacts')->where('contact_id', $contactId)->update($fields);
    }
}
