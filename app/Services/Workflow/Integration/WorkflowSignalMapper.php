<?php

namespace App\Services\Workflow\Integration;

final class WorkflowSignalMapper
{
    /**
     * Map a normalized workflow-facing event into action-intent output.
     *
     * This is still orchestration-level behavior. It does not execute the actions.
     * It only decides what the action meaning should be.
     */
    public function mapEmailSignalToActions(array $normalizedEvent): array
    {
        $eventType = $normalizedEvent['event_type'] ?? null;
        $subjectId = $normalizedEvent['subject_id'] ?? null;

        return match ($eventType) {
            'EMAIL_LINK_CLICKED' => [
                [
                    'action_type' => 'APPLY_LEAD_SCORE',
                    'target_type' => 'CONTACT',
                    'target_id' => $subjectId,
                    'payload' => [
                        'score_rule_code' => 'EMAIL_CLICK',
                    ],
                ],
                [
                    'action_type' => 'UPDATE_LEAD_SUMMARY',
                    'target_type' => 'CONTACT',
                    'target_id' => $subjectId,
                    'payload' => [
                        'summary_code' => 'EMAIL_CLICKED',
                    ],
                ],
            ],

            'EMAIL_OPENED' => [
                [
                    'action_type' => 'APPLY_LEAD_SCORE',
                    'target_type' => 'CONTACT',
                    'target_id' => $subjectId,
                    'payload' => [
                        'score_rule_code' => 'EMAIL_OPEN',
                    ],
                ],
                [
                    'action_type' => 'UPDATE_LEAD_SUMMARY',
                    'target_type' => 'CONTACT',
                    'target_id' => $subjectId,
                    'payload' => [
                        'summary_code' => 'EMAIL_OPENED',
                    ],
                ],
            ],

            'EMAIL_BOUNCED' => [
                [
                    'action_type' => 'UPDATE_LEAD_SUMMARY',
                    'target_type' => 'CONTACT',
                    'target_id' => $subjectId,
                    'payload' => [
                        'summary_code' => 'EMAIL_BOUNCED',
                    ],
                ],
            ],

            default => [],
        };
    }
}
