<?php

namespace App\Services\Workflow\Authoring;

use App\Models\WorkflowVersion;

class WorkflowGraphValidator
{
    /**
     * Validate a stored workflow version model.
     *
     * Returns a structured result so callers can show readable validation output
     * without relying on exceptions for normal validation failures.
     */
    public function validateWorkflowVersion(WorkflowVersion $workflowVersion): array
    {
        return $this->validateDefinition(
            profileCode: $workflowVersion->WorkflowProfileCode ?? null,
            stepGraph: $workflowVersion->StepGraphJson ?? [],
            actionConfig: $workflowVersion->ActionConfigJson ?? []
        );
    }

    /**
     * Validate the workflow definition pieces that matter for current branch safety.
     *
     * We deliberately validate the current v3.13 JSON structure as-is to avoid a
     * noisy rewrite in this step.
     */
    public function validateDefinition(
        ?string $profileCode,
        array $stepGraph,
        array $actionConfig
    ): array {
        $errors = [];

        $catalog = config('workflow_catalog');

        $this->validateProfileCode($profileCode, $catalog, $errors);
        $this->validateStepGraphRoot($stepGraph, $errors);

        $steps = $stepGraph['steps'] ?? [];
        $initialStep = $stepGraph['initial_step'] ?? null;

        $stepIndex = $this->buildStepIndex($steps, $errors);
        $this->validateInitialStep($initialStep, $stepIndex, $errors);

        foreach ($steps as $step) {
            $this->validateStep($step, $stepIndex, $catalog, $errors);
        }

        $this->validateActionConfig($actionConfig, $stepIndex, $catalog, $errors);

        return [
            'ok' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function validateProfileCode(?string $profileCode, array $catalog, array &$errors): void
    {
        if (! is_string($profileCode) || trim($profileCode) === '') {
            $errors[] = 'WorkflowProfileCode is required.';

            return;
        }

        if (! in_array($profileCode, $catalog['profiles'], true)) {
            $errors[] = "Unsupported WorkflowProfileCode [{$profileCode}].";
        }
    }

    protected function validateStepGraphRoot(array $stepGraph, array &$errors): void
    {
        if ($stepGraph === []) {
            $errors[] = 'StepGraphJson is required.';

            return;
        }

        $initialStep = $stepGraph['initial_step'] ?? null;
        $steps = $stepGraph['steps'] ?? null;

        if (! is_string($initialStep) || trim($initialStep) === '') {
            $errors[] = 'StepGraphJson.initial_step is required.';
        }

        if (! is_array($steps) || $steps === []) {
            $errors[] = 'StepGraphJson.steps must be a non-empty array.';
        }
    }

    protected function buildStepIndex(array $steps, array &$errors): array
    {
        $stepIndex = [];

        foreach ($steps as $position => $step) {
            $stepKey = $step['key'] ?? null;

            if (! is_string($stepKey) || trim($stepKey) === '') {
                $errors[] = "Step at position [{$position}] is missing key.";

                continue;
            }

            if (array_key_exists($stepKey, $stepIndex)) {
                $errors[] = "Duplicate step key [{$stepKey}].";

                continue;
            }

            $stepIndex[$stepKey] = $step;
        }

        return $stepIndex;
    }

    protected function validateInitialStep(?string $initialStep, array $stepIndex, array &$errors): void
    {
        if (! is_string($initialStep) || trim($initialStep) === '') {
            return;
        }

        if (! array_key_exists($initialStep, $stepIndex)) {
            $errors[] = "Initial step [{$initialStep}] does not exist in StepGraphJson.steps.";
        }
    }

    protected function validateStep(array $step, array $stepIndex, array $catalog, array &$errors): void
    {
        $stepKey = $step['key'] ?? '[missing-key]';
        $stepType = $step['type'] ?? null;

        if (! is_string($stepType) || trim($stepType) === '') {
            $errors[] = "Step [{$stepKey}] is missing type.";

            return;
        }

        if (! in_array($stepType, $catalog['step_types'], true)) {
            $errors[] = "Step [{$stepKey}] uses unsupported type [{$stepType}].";

            return;
        }

        if ($stepType === 'WAIT_FOR_EVENT') {
            $this->validateWaitForEventStep($step, $catalog, $errors);
        }

        if ($stepType === 'WAIT_FOR_TIME') {
            $this->validateWaitForTimeStep($step, $errors);
        }

        $this->validateNextStepReference($step, $stepIndex, $errors);
        $this->validateTerminalFlags($step, $errors);
    }

    protected function validateWaitForEventStep(array $step, array $catalog, array &$errors): void
    {
        $stepKey = $step['key'] ?? '[missing-key]';
        $conditions = $step['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            $errors[] = "WAIT_FOR_EVENT step [{$stepKey}] must have a non-empty conditions array.";

            return;
        }

        foreach ($conditions as $conditionIndex => $condition) {
            $conditionType = $condition['type'] ?? null;
            $config = $condition['config'] ?? null;

            if (! is_string($conditionType) || trim($conditionType) === '') {
                $errors[] = "Step [{$stepKey}] has a condition at index [{$conditionIndex}] with missing type.";

                continue;
            }

            if (! in_array($conditionType, $catalog['condition_types'], true)) {
                $errors[] = "Step [{$stepKey}] uses unsupported condition type [{$conditionType}].";

                continue;
            }

            if (! is_array($config)) {
                $errors[] = "Step [{$stepKey}] condition [{$conditionType}] must include config array.";

                continue;
            }

            if (in_array($conditionType, ['event_type_in', 'event_category_in', 'event_source_in'], true)) {
                $values = $config['values'] ?? null;

                if (! is_array($values) || $values === []) {
                    $errors[] = "Step [{$stepKey}] condition [{$conditionType}] requires a non-empty config.values array.";
                } else {
                    $this->validateCatalogBackedConditionValues(
                        stepKey: $stepKey,
                        conditionType: $conditionType,
                        values: $values,
                        catalog: $catalog,
                        errors: $errors
                    );
                }
            }

            if ($conditionType === 'payload_field_exists') {
                $field = $config['field'] ?? null;

                if (! is_string($field) || trim($field) === '') {
                    $errors[] = "Step [{$stepKey}] condition [payload_field_exists] requires config.field.";
                }
            }
        }
    }

    protected function validateCatalogBackedConditionValues(
        string $stepKey,
        string $conditionType,
        array $values,
        array $catalog,
        array &$errors
    ): void {
        $allowedValues = match ($conditionType) {
            'event_type_in' => $catalog['event_types'],
            'event_category_in' => $catalog['event_categories'],
            'event_source_in' => $catalog['source_systems'],
            default => [],
        };

        $this->validateKnownStringValues(
            stepKey: $stepKey,
            conditionType: $conditionType,
            values: $values,
            allowedValues: $allowedValues,
            errors: $errors
        );
    }

    protected function validateKnownStringValues(
        string $stepKey,
        string $conditionType,
        array $values,
        array $allowedValues,
        array &$errors
    ): void {
        foreach ($values as $valueIndex => $value) {
            if (! is_string($value) || trim($value) === '') {
                $errors[] = "Step [{$stepKey}] condition [{$conditionType}] has an invalid value at index [{$valueIndex}].";

                continue;
            }

            if (! in_array($value, $allowedValues, true)) {
                $errors[] = "Step [{$stepKey}] condition [{$conditionType}] uses unsupported value [{$value}].";
            }
        }
    }

    protected function validateWaitForTimeStep(array $step, array &$errors): void
    {
        $stepKey = $step['key'] ?? '[missing-key]';
        $waitConfig = $step['wait_config'] ?? null;

        if (! is_array($waitConfig)) {
            $errors[] = "WAIT_FOR_TIME step [{$stepKey}] must include wait_config.";

            return;
        }

        $mode = $waitConfig['mode'] ?? null;
        $value = $waitConfig['value'] ?? null;

        if ($mode !== 'DELAY_MINUTES') {
            $errors[] = "WAIT_FOR_TIME step [{$stepKey}] currently supports only wait_config.mode = DELAY_MINUTES.";
        }

        if (! is_int($value) || $value <= 0) {
            $errors[] = "WAIT_FOR_TIME step [{$stepKey}] requires wait_config.value to be a positive integer.";
        }
    }

    protected function validateNextStepReference(array $step, array $stepIndex, array &$errors): void
    {
        $stepKey = $step['key'] ?? '[missing-key]';
        $next = $step['next'] ?? null;

        if ($next === null) {
            return;
        }

        if (! is_string($next) || trim($next) === '') {
            $errors[] = "Step [{$stepKey}] has an invalid next value.";

            return;
        }

        if (! array_key_exists($next, $stepIndex)) {
            $errors[] = "Step [{$stepKey}] points to missing next step [{$next}].";
        }
    }

    protected function validateTerminalFlags(array $step, array &$errors): void
    {
        $stepKey = $step['key'] ?? '[missing-key]';
        $isTerminal = (bool) ($step['terminal'] ?? false);
        $next = $step['next'] ?? null;
        $type = $step['type'] ?? null;

        if ($type === 'TERMINAL' && $next !== null) {
            $errors[] = "TERMINAL step [{$stepKey}] must not define next.";
        }

        if ($isTerminal && $next !== null) {
            $errors[] = "Step [{$stepKey}] is marked terminal but still defines next.";
        }
    }

    protected function validateActionConfig(array $actionConfig, array $stepIndex, array $catalog, array &$errors): void
    {
        if ($actionConfig === []) {
            return;
        }

        $onStepCompletion = $actionConfig['on_step_completion'] ?? null;

        if ($onStepCompletion === null) {
            $errors[] = 'ActionConfigJson must use on_step_completion root key when actions are configured.';

            return;
        }

        if (! is_array($onStepCompletion)) {
            $errors[] = 'ActionConfigJson.on_step_completion must be an array.';

            return;
        }

        foreach ($onStepCompletion as $stepKey => $actions) {
            if (! array_key_exists($stepKey, $stepIndex)) {
                $errors[] = "ActionConfigJson references unknown step [{$stepKey}].";

                continue;
            }

            if (! is_array($actions)) {
                $errors[] = "ActionConfigJson step [{$stepKey}] must contain an actions array.";

                continue;
            }

            foreach ($actions as $actionIndex => $action) {
                $actionType = $action['action_type'] ?? null;

                if (! is_string($actionType) || trim($actionType) === '') {
                    $errors[] = "ActionConfigJson step [{$stepKey}] action index [{$actionIndex}] is missing action_type.";

                    continue;
                }

                if (! in_array($actionType, $catalog['action_types'], true)) {
                    $errors[] = "ActionConfigJson step [{$stepKey}] uses unsupported action_type [{$actionType}].";

                    continue;
                }

                $this->validateActionShape(
                    stepKey: $stepKey,
                    actionType: $actionType,
                    action: $action,
                    catalog: $catalog,
                    errors: $errors
                );
            }
        }
    }

    protected function validateActionShape(
        string $stepKey,
        string $actionType,
        array $action,
        array $catalog,
        array &$errors
    ): void {
        $rules = $catalog['action_rules'][$actionType] ?? null;

        if (! is_array($rules)) {
            return;
        }

        $targetType = $action['target_type'] ?? null;
        $payload = $action['payload'] ?? null;

        $this->validateActionTargetType(
            stepKey: $stepKey,
            actionType: $actionType,
            targetType: $targetType,
            rules: $rules,
            errors: $errors
        );

        $this->validateActionPayloadKeys(
            stepKey: $stepKey,
            actionType: $actionType,
            payload: $payload,
            rules: $rules,
            errors: $errors
        );
    }

    protected function validateActionTargetType(
        string $stepKey,
        string $actionType,
        mixed $targetType,
        array $rules,
        array &$errors
    ): void {
        $allowedTargetTypes = $rules['allowed_target_types'] ?? [];

        if ($allowedTargetTypes === []) {
            return;
        }

        if (! is_string($targetType) || trim($targetType) === '') {
            $errors[] = "ActionConfigJson step [{$stepKey}] action [{$actionType}] requires target_type.";

            return;
        }

        if (! in_array($targetType, $allowedTargetTypes, true)) {
            $errors[] = "ActionConfigJson step [{$stepKey}] action [{$actionType}] uses unsupported target_type [{$targetType}].";
        }
    }

    protected function validateActionPayloadKeys(
        string $stepKey,
        string $actionType,
        mixed $payload,
        array $rules,
        array &$errors
    ): void {
        $requiredPayloadKeys = $rules['required_payload_keys'] ?? [];

        if ($requiredPayloadKeys === []) {
            return;
        }

        if (! is_array($payload)) {
            $errors[] = "ActionConfigJson step [{$stepKey}] action [{$actionType}] requires payload array.";

            return;
        }

        foreach ($requiredPayloadKeys as $requiredKey) {
            if (! array_key_exists($requiredKey, $payload)) {
                $errors[] = "ActionConfigJson step [{$stepKey}] action [{$actionType}] is missing required payload key [{$requiredKey}].";
            }
        }
    }
}
