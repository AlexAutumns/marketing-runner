<?php

namespace App\Services\Workflow\Integration;

final class DomainUpdateInstructionBuilder
{
    /**
     * Build domain update instructions from mapped workflow actions.
     *
     * This does not perform database writes. It only shapes the next intended
     * domain updates in a clear, structured form.
     */
    public function buildFromMappedActions(
        array $mappedActions,
        array $normalizedEvent
    ): array {
        $instructions = [];

        foreach ($mappedActions as $action) {
            $actionType = $action['action_type'] ?? null;

            $instruction = match ($actionType) {
                'APPLY_LEAD_SCORE' => $this->buildLeadScoreInstruction($action, $normalizedEvent),
                'UPDATE_LEAD_SUMMARY' => $this->buildLeadSummaryInstruction($action, $normalizedEvent),
                default => null,
            };

            if ($instruction !== null) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    protected function buildLeadScoreInstruction(array $action, array $normalizedEvent): array
    {
        return [
            'instruction_version' => 1,
            'instruction_type' => 'UPDATE_CONTACT_LEAD_SCORE',
            'subject_type' => 'CONTACT',
            'subject_id' => $action['target_id'] ?? $normalizedEvent['subject_id'] ?? null,
            'workflow_context' => $normalizedEvent['workflow_context'] ?? [],
            'reason' => [
                'source_event_type' => $normalizedEvent['event_type'] ?? null,
                'source_system' => $normalizedEvent['source_system'] ?? null,
            ],
            'changes' => [
                'score_rule_code' => $action['payload']['score_rule_code'] ?? null,
            ],
        ];
    }

    protected function buildLeadSummaryInstruction(array $action, array $normalizedEvent): array
    {
        return [
            'instruction_version' => 1,
            'instruction_type' => 'UPDATE_CONTACT_LEAD_SUMMARY',
            'subject_type' => 'CONTACT',
            'subject_id' => $action['target_id'] ?? $normalizedEvent['subject_id'] ?? null,
            'workflow_context' => $normalizedEvent['workflow_context'] ?? [],
            'reason' => [
                'source_event_type' => $normalizedEvent['event_type'] ?? null,
                'source_system' => $normalizedEvent['source_system'] ?? null,
            ],
            'changes' => [
                'summary_code' => $action['payload']['summary_code'] ?? null,
            ],
        ];
    }
}
