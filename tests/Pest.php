<?php

use App\Models\WorkflowEnrollment;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Workflow tests live under tests/Feature/Workflow and use the application
| container through Tests\TestCase. Pest stays concise, but the structure
| remains deliberate and suite-friendly.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Shared workflow test helpers
|--------------------------------------------------------------------------
|
| These helpers are centralized here so workflow test files do not redeclare
| the same functions repeatedly. This keeps the suite cleaner and prevents
| fatal redeclaration errors.
|
*/

function firstWorkflowTestContactId(): string
{
    return DB::table('contacts')->value('contact_id');
}

function enrollEmailFirstWorkflow($testCase, string $contactId): WorkflowEnrollment
{
    $testCase->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    return WorkflowEnrollment::query()->firstOrFail();
}

function moveBaselineWorkflowIntoWaiting($testCase, string $contactId, string $correlationKey): WorkflowEnrollment
{
    $testCase->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_001',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();

    $testCase->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_001',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => $correlationKey,
    ])->assertExitCode(0);

    $testCase->artisan('workflow:process')->assertExitCode(0);

    return $enrollment->refresh();
}

function prepareQueuedActionContext($testCase): array
{
    $contactId = firstWorkflowTestContactId();

    $testCase->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();
    $correlationKey = 'CORR_TEST_EXPORT_001';

    $testCase->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => $correlationKey,
    ])->assertExitCode(0);

    $testCase->artisan('workflow:process')->assertExitCode(0);

    return [
        'contact_id' => $contactId,
        'enrollment_id' => $enrollment->EnrollmentID,
        'correlation_key' => $correlationKey,
    ];
}
