<?php

namespace App\Services\Workflow\Integration;

final class IntegrationEventNormalizer
{
    /**
     * Normalize an email-tracking style payload into a workflow-facing event shape.
     *
     * This does not execute workflow logic. It only translates external signal
     * shape into one internal, readable structure.
     */
    public function normalizeEmailTrackingEvent(array $payload): array
    {
        $externalEventType = $payload['event_type'] ?? null;

        $normalizedEventType = match ($externalEventType) {
            'clicked', 'EMAIL_LINK_CLICKED' => 'EMAIL_LINK_CLICKED',
            'opened', 'EMAIL_OPENED' => 'EMAIL_OPENED',
            'delivered', 'EMAIL_DELIVERED' => 'EMAIL_DELIVERED',
            'bounced', 'EMAIL_BOUNCED' => 'EMAIL_BOUNCED',
            default => 'UNKNOWN_EVENT',
        };

        return [
            'contract_version' => 1,
            'event_type' => $normalizedEventType,
            'event_category' => 'ENGAGEMENT',
            'source_system' => 'EMAIL_TRACKING',
            'subject_type' => 'CONTACT',
            'subject_id' => $payload['contact_id'] ?? null,
            'occurred_at_utc' => $payload['occurred_at_utc'] ?? now()->toISOString(),
            'workflow_context' => [
                'workflow_id' => $payload['workflow_id'] ?? null,
                'workflow_version_id' => $payload['workflow_version_id'] ?? null,
                'enrollment_id' => $payload['enrollment_id'] ?? null,
                'correlation_key' => $payload['correlation_key'] ?? null,
            ],
            'attributes' => [
                'message_id' => $payload['message_id'] ?? null,
                'link_url' => $payload['link_url'] ?? null,
                'raw_event_type' => $externalEventType,
            ],
        ];
    }
}
