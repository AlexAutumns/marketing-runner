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

it('validates the seeded email-first workflow version', function () {
    $workflowVersion = WorkflowVersion::query()->findOrFail('WFLV_002');

    $result = app(WorkflowGraphValidator::class)
        ->validateWorkflowVersion($workflowVersion);

    expect($result['ok'])->toBeTrue()
        ->and($result['errors'])->toBeArray()
        ->and($result['errors'])->toBe([]);
});

it('blocks publishing an unchanged cloned draft', function () {
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

it('publishes a changed cloned draft successfully', function () {
    $sourceVersion = WorkflowVersion::query()->findOrFail('WFLV_002');

    $service = app(WorkflowVersionService::class);
    $draftVersion = $service->cloneToDraft(
        $sourceVersion,
        'Test clone with meaningful change'
    );

    $stepGraph = $draftVersion->StepGraphJson ?? [];

    foreach ($stepGraph['steps'] as $stepIndex => $step) {
        if (($step['key'] ?? null) !== 'AWAIT_EMAIL_SIGNAL') {
            continue;
        }

        foreach (($step['conditions'] ?? []) as $conditionIndex => $condition) {
            if (($condition['type'] ?? null) !== 'event_type_in') {
                continue;
            }

            $stepGraph['steps'][$stepIndex]['conditions'][$conditionIndex]['config']['values'][] = 'EMAIL_OPENED';
        }
    }

    $draftVersion->StepGraphJson = $stepGraph;
    $draftVersion->GraphDefinitionHash = null;
    $draftVersion->save();

    $publishedVersion = $service->publishDraft(
        $draftVersion,
        'Publish changed clone that now accepts EMAIL_OPENED'
    );

    expect($publishedVersion->VersionStatusCode)->toBe('PUBLISHED')
        ->and($publishedVersion->GraphDefinitionHash)->not->toBeNull()
        ->and($publishedVersion->GraphDefinitionHash)->not->toBe($sourceVersion->GraphDefinitionHash);
});
