<?php

namespace App\Services\Workflow\Integration;

use App\Models\WorkflowActionQueue;

final class QueuedActionBundleProjector
{
    public function __construct(
        protected QueuedActionInstructionProjector $instructionProjector
    ) {}

    /**
     * Build one bundle from a set of related queued actions.
     *
     * This does not execute anything. It shapes the related queue rows into
     * one cleaner downstream-facing package.
     */
    public function project(array $actionQueueRows): array
    {
        if ($actionQueueRows === []) {
            return [
                'bundle_version' => 1,
                'bundle_type' => 'CONTACT_UPDATE_BUNDLE',
                'workflow_context' => [],
                'subject' => [],
                'source_action_ids' => [],
                'instructions' => [],
            ];
        }

        /** @var WorkflowActionQueue $first */
        $first = $actionQueueRows[0];

        $instructions = [];
        $sourceActionIds = [];

        foreach ($actionQueueRows as $actionQueue) {
            if (! $actionQueue instanceof WorkflowActionQueue) {
                continue;
            }

            $sourceActionIds[] = $actionQueue->ActionQueueID;

            $instruction = $this->instructionProjector->project($actionQueue);

            if ($instruction !== null) {
                $instructions[] = $instruction;
            }
        }

        return [
            'bundle_version' => 1,
            'bundle_type' => 'CONTACT_UPDATE_BUNDLE',
            'workflow_context' => [
                'workflow_id' => $first->WorkflowID,
                'workflow_version_id' => $first->WorkflowVersionID,
                'enrollment_id' => $first->EnrollmentID,
                'correlation_key' => $first->CorrelationKey,
            ],
            'subject' => [
                'subject_type' => $first->TargetTypeCode,
                'subject_id' => $first->TargetID,
            ],
            'source_action_ids' => $sourceActionIds,
            'instructions' => $instructions,
        ];
    }
}
