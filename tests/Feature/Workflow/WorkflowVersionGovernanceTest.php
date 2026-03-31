<?php

use App\Models\WorkflowVersion;
use App\Services\Workflow\Authoring\WorkflowGraphValidator;
use App\Services\Workflow\Authoring\WorkflowVersionService;
use Database\Seeders\WorkflowFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WorkflowFoundationSeeder::class);
});

it('validates the seeded email-first workflow version because its stored definition is currently well formed', function () {
    $workflowVersion = WorkflowVersion::query()->findOrFail('WFLV_002');

    $result = app(WorkflowGraphValidator::class)
        ->validateWorkflowVersion($workflowVersion);

    expect($result['ok'])->toBeTrue()
        ->and($result['errors'])->toBeArray()
        ->and($result['errors'])->toBe([]);
});

it('stores real definition hashes on seeded reference versions after seeding', function () {
    $baselineVersion = WorkflowVersion::query()->findOrFail('WFLV_001');
    $emailFirstVersion = WorkflowVersion::query()->findOrFail('WFLV_002');

    expect($baselineVersion->GraphDefinitionHash)->not->toBeNull()
        ->and($baselineVersion->GraphDefinitionHash)->toHaveLength(64)
        ->and($emailFirstVersion->GraphDefinitionHash)->not->toBeNull()
        ->and($emailFirstVersion->GraphDefinitionHash)->toHaveLength(64);
});

it('blocks publishing an unchanged cloned draft because the definition already exists in an active or published state', function () {
    $sourceVersion = WorkflowVersion::query()->findOrFail('WFLV_002');

    $service = app(WorkflowVersionService::class);
    $draftVersion = $service->cloneToDraft(
        $sourceVersion,
        'Test clone without meaningful change'
    );

    expect(fn () => $service->publishDraft(
        $draftVersion,
        'Attempt to publish unchanged clone'
    ))->toThrow(
        DomainException::class,
        'An identical active or published workflow version already exists.'
    );
});

it('publishes a changed cloned draft successfully when the step graph meaning is actually different', function () {
    $sourceVersion = WorkflowVersion::query()->findOrFail('WFLV_002');

    $service = app(WorkflowVersionService::class);
    $draftVersion = $service->cloneToDraft(
        $sourceVersion,
        'Test clone with meaningful change'
    );

    replaceConditionValues(
        workflowVersion: $draftVersion,
        stepKey: 'AWAIT_EMAIL_SIGNAL',
        conditionType: 'event_type_in',
        values: [
            'MANUAL_TEST_EVENT',
            'EMAIL_LINK_CLICKED',
            'EMAIL_OPENED',
        ]
    );

    $publishedVersion = $service->publishDraft(
        $draftVersion,
        'Publish changed clone that now accepts EMAIL_OPENED'
    );

    expect($publishedVersion->VersionStatusCode)->toBe('PUBLISHED')
        ->and($publishedVersion->GraphDefinitionHash)->not->toBeNull()
        ->and($publishedVersion->GraphDefinitionHash)->not->toBe($sourceVersion->GraphDefinitionHash);
});

it('rejects a draft that uses an unsupported event_type_in value', function () {
    $draftVersion = cloneWorkflowVersionForMutation('WFLV_002');

    replaceConditionValues(
        workflowVersion: $draftVersion,
        stepKey: 'AWAIT_EMAIL_SIGNAL',
        conditionType: 'event_type_in',
        values: [
            'EMAIL_DOES_NOT_EXIST',
        ]
    );

    $result = app(WorkflowGraphValidator::class)
        ->validateWorkflowVersion($draftVersion);

    expect($result['ok'])->toBeFalse()
        ->and(validationErrorsContain(
            $result['errors'],
            'unsupported value [EMAIL_DOES_NOT_EXIST]'
        ))->toBeTrue();
});

it('rejects a draft when APPLY_LEAD_SCORE is missing the required score_rule_code payload key', function () {
    $draftVersion = cloneWorkflowVersionForMutation('WFLV_002');

    removeActionPayloadKey(
        workflowVersion: $draftVersion,
        stepKey: 'AWAIT_EMAIL_SIGNAL',
        actionType: 'APPLY_LEAD_SCORE',
        payloadKey: 'score_rule_code'
    );

    $result = app(WorkflowGraphValidator::class)
        ->validateWorkflowVersion($draftVersion);

    expect($result['ok'])->toBeFalse()
        ->and(validationErrorsContain(
            $result['errors'],
            'missing required payload key [score_rule_code]'
        ))->toBeTrue();
});

it('rejects a draft when APPLY_LEAD_SCORE uses an unsupported target type', function () {
    $draftVersion = cloneWorkflowVersionForMutation('WFLV_002');

    setActionTargetType(
        workflowVersion: $draftVersion,
        stepKey: 'AWAIT_EMAIL_SIGNAL',
        actionType: 'APPLY_LEAD_SCORE',
        targetType: 'SCORING_SERVICE'
    );

    $result = app(WorkflowGraphValidator::class)
        ->validateWorkflowVersion($draftVersion);

    expect($result['ok'])->toBeFalse()
        ->and(validationErrorsContain(
            $result['errors'],
            'uses unsupported target_type [SCORING_SERVICE]'
        ))->toBeTrue();
});

it('rejects a draft when a WAIT_FOR_TIME step has an invalid wait_config', function () {
    $draftVersion = cloneWorkflowVersionForMutation('WFLV_001');

    breakWaitConfig(
        workflowVersion: $draftVersion,
        stepKey: 'WAIT_BEFORE_STRONGER_SIGNAL'
    );

    $result = app(WorkflowGraphValidator::class)
        ->validateWorkflowVersion($draftVersion);

    expect($result['ok'])->toBeFalse()
        ->and(validationErrorsContain(
            $result['errors'],
            'wait_config'
        ))->toBeTrue();
});

function cloneWorkflowVersionForMutation(string $workflowVersionId): WorkflowVersion
{
    $sourceVersion = WorkflowVersion::query()->findOrFail($workflowVersionId);

    return app(WorkflowVersionService::class)->cloneToDraft(
        $sourceVersion,
        'Draft cloned for validation mutation test'
    );
}

function replaceConditionValues(
    WorkflowVersion $workflowVersion,
    string $stepKey,
    string $conditionType,
    array $values
): void {
    $stepGraph = $workflowVersion->StepGraphJson ?? [];

    foreach ($stepGraph['steps'] as $stepIndex => $step) {
        if (($step['key'] ?? null) !== $stepKey) {
            continue;
        }

        foreach (($step['conditions'] ?? []) as $conditionIndex => $condition) {
            if (($condition['type'] ?? null) !== $conditionType) {
                continue;
            }

            $stepGraph['steps'][$stepIndex]['conditions'][$conditionIndex]['config']['values'] = $values;
        }
    }

    $workflowVersion->StepGraphJson = $stepGraph;
    $workflowVersion->GraphDefinitionHash = null;
    $workflowVersion->save();
}

function removeActionPayloadKey(
    WorkflowVersion $workflowVersion,
    string $stepKey,
    string $actionType,
    string $payloadKey
): void {
    $actionConfig = $workflowVersion->ActionConfigJson ?? [];
    $actions = $actionConfig['on_step_completion'][$stepKey] ?? [];

    foreach ($actions as $actionIndex => $action) {
        if (($action['action_type'] ?? null) !== $actionType) {
            continue;
        }

        unset($actionConfig['on_step_completion'][$stepKey][$actionIndex]['payload'][$payloadKey]);
    }

    $workflowVersion->ActionConfigJson = $actionConfig;
    $workflowVersion->GraphDefinitionHash = null;
    $workflowVersion->save();
}

function setActionTargetType(
    WorkflowVersion $workflowVersion,
    string $stepKey,
    string $actionType,
    string $targetType
): void {
    $actionConfig = $workflowVersion->ActionConfigJson ?? [];
    $actions = $actionConfig['on_step_completion'][$stepKey] ?? [];

    foreach ($actions as $actionIndex => $action) {
        if (($action['action_type'] ?? null) !== $actionType) {
            continue;
        }

        $actionConfig['on_step_completion'][$stepKey][$actionIndex]['target_type'] = $targetType;
    }

    $workflowVersion->ActionConfigJson = $actionConfig;
    $workflowVersion->GraphDefinitionHash = null;
    $workflowVersion->save();
}

function breakWaitConfig(
    WorkflowVersion $workflowVersion,
    string $stepKey
): void {
    $stepGraph = $workflowVersion->StepGraphJson ?? [];

    foreach ($stepGraph['steps'] as $stepIndex => $step) {
        if (($step['key'] ?? null) !== $stepKey) {
            continue;
        }

        $stepGraph['steps'][$stepIndex]['wait_config'] = [
            'mode' => 'DELAY_MINUTES',
        ];
    }

    $workflowVersion->StepGraphJson = $stepGraph;
    $workflowVersion->GraphDefinitionHash = null;
    $workflowVersion->save();
}

function validationErrorsContain(array $errors, string $needle): bool
{
    return collect($errors)->contains(
        fn (string $error) => str_contains($error, $needle)
    );
}
