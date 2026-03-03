<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface MarketingStorage
{
    /** @return Collection<int, object> contacts with at least contact_id, personal_email, first_name, lifecycle_stage, lead_status */
    public function listContacts(): Collection;

    /** @return object|null latest activity row for a contact */
    public function getLatestActivity(string $contactId): ?object;

    /** Returns true if contact has complied for this tracking id (or fallback rule) */
    public function hasComplied(string $contactId, ?string $trackingId, ?string $lastSendTime): bool;

    /** Insert a send activity row */
    public function insertActivity(array $activityRow): void;

    /** Find contact by email */
    public function findContactByEmail(string $email): ?object;

    /** Insert an engagement row (COMPLIED/CLICKED/etc.) */
    public function insertEngagement(array $engagementRow): void;

    /** Update contact fields when complied (lead_status/lifecycle_stage) */
    public function updateContactOnComply(string $contactId, array $fields): void;
}
