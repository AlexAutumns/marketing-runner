<?php

namespace App\Services\Workflow\Integration;

final class BundleConsumePathBuilder
{
    public function build(array $bundle): array
    {
        return [
            'consume_path_version' => 1,
            'bundle_type' => $bundle['bundle_type'] ?? null,
            'local_demo_application' => $this->buildLocalDemoApplication($bundle),
            'crm_mvp_handoff' => $this->buildCrmMvpHandoff($bundle),
        ];
    }

    protected function buildLocalDemoApplication(array $bundle): array
    {
        $subject = $bundle['subject'] ?? [];
        $instructions = $bundle['instructions'] ?? [];

        $scoreRuleCodes = [];
        $summaryCodes = [];

        foreach ($instructions as $instruction) {
            $instructionType = $instruction['instruction_type'] ?? null;
            $changes = $instruction['changes'] ?? [];

            if ($instructionType === 'UPDATE_CONTACT_LEAD_SCORE') {
                $scoreRuleCode = $changes['score_rule_code'] ?? null;

                if (is_string($scoreRuleCode) && $scoreRuleCode !== '') {
                    $scoreRuleCodes[] = $scoreRuleCode;
                }
            }

            if ($instructionType === 'UPDATE_CONTACT_LEAD_SUMMARY') {
                $summaryCode = $changes['summary_code'] ?? null;

                if (is_string($summaryCode) && $summaryCode !== '') {
                    $summaryCodes[] = $summaryCode;
                }
            }
        }

        return [
            'subject_type' => $subject['subject_type'] ?? null,
            'subject_id' => $subject['subject_id'] ?? null,
            'score_updates' => array_values(array_unique($scoreRuleCodes)),
            'summary_updates' => array_values(array_unique($summaryCodes)),
            'notes' => 'Thin local/demo consume view. This is intentionally not the final CRM persistence path.',
        ];
    }

    protected function buildCrmMvpHandoff(array $bundle): array
    {
        $workflowContext = $bundle['workflow_context'] ?? [];
        $subject = $bundle['subject'] ?? [];
        $instructions = $bundle['instructions'] ?? [];
        $rules = config('workflow_catalog.crm_mvp_handoff_rules', []);

        $serviceActions = [];

        foreach ($instructions as $instruction) {
            $instructionType = $instruction['instruction_type'] ?? null;
            $changes = $instruction['changes'] ?? [];
            $rule = $rules[$instructionType] ?? null;

            if (! is_array($rule)) {
                continue;
            }

            $referenceKey = $rule['reference_key'] ?? null;
            $referenceLabel = $rule['reference_label'] ?? null;
            $referenceValue = $referenceKey ? ($changes[$referenceKey] ?? null) : null;

            $serviceActions[] = [
                'target_type_code' => $rule['target_type_code'] ?? null,
                'target_id' => $rule['target_id'] ?? null,
                'instruction_type' => $instructionType,
                'payload' => $changes,
                'references' => [
                    $referenceLabel => $referenceValue,
                ],
            ];
        }

        return [
            'handoff_version' => 1,
            'handoff_type' => 'CRM_MVP_WORKFLOW_HANDOFF',
            'workflow_context' => $workflowContext,
            'subject' => $subject,
            'source_action_ids' => $bundle['source_action_ids'] ?? [],
            'service_actions' => $serviceActions,
        ];
    }
}
