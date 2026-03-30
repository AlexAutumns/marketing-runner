<?php

namespace App\Services\Workflow\Integration;

use App\Models\WorkflowActionQueue;

final class QueuedActionInstructionProjector
{
    /**
     * Project a real queued workflow action into a structured domain instruction.
     *
     * This does not execute the instruction. It only interprets the queue row into
     * a cleaner downstream-facing shape.
     */
    public function project(WorkflowActionQueue $actionQueue): ?array
    {
        return match ($actionQueue->ActionTypeCode) {
            'APPLY_LEAD_SCORE' => $this->projectLeadScoreInstruction($actionQueue),
            'UPDATE_LEAD_SUMMARY' => $this->projectLeadSummaryInstruction($actionQueue),
            default => null,
        };
    }

    protected function projectLeadScoreInstruction(WorkflowActionQueue $actionQueue): array
    {
        return [
            'instruction_version' => 1,
            'instruction_type' => 'UPDATE_CONTACT_LEAD_SCORE',
            'subject_type' => $actionQueue->TargetTypeCode,
            'subject_id' => $actionQueue->TargetID,
            'workflow_context' => [
                'workflow_id' => $actionQueue->WorkflowID,
                'workflow_version_id' => $actionQueue->WorkflowVersionID,
                'enrollment_id' => $actionQueue->EnrollmentID,
                'correlation_key' => $actionQueue->CorrelationKey,
                'related_event_id' => $actionQueue->RelatedEventID,
            ],
            'changes' => [
                'score_rule_code' => $actionQueue->PayloadJson['score_rule_code'] ?? null,
            ],
        ];
    }

    protected function projectLeadSummaryInstruction(WorkflowActionQueue $actionQueue): array
    {
        return [
            'instruction_version' => 1,
            'instruction_type' => 'UPDATE_CONTACT_LEAD_SUMMARY',
            'subject_type' => $actionQueue->TargetTypeCode,
            'subject_id' => $actionQueue->TargetID,
            'workflow_context' => [
                'workflow_id' => $actionQueue->WorkflowID,
                'workflow_version_id' => $actionQueue->WorkflowVersionID,
                'enrollment_id' => $actionQueue->EnrollmentID,
                'correlation_key' => $actionQueue->CorrelationKey,
                'related_event_id' => $actionQueue->RelatedEventID,
            ],
            'changes' => [
                'summary_code' => $actionQueue->PayloadJson['summary_code'] ?? null,
            ],
        ];
    }
}
