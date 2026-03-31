<?php

use App\Models\WorkflowActionQueue;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowEventInbox;
use App\Models\WorkflowStepLog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('creates an active enrollment at the configured initial step and writes the initial step log', function () {
    $contactId = firstWorkflowTestContactId();

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();
    $stepLogs = WorkflowStepLog::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('OccurredAtUTC')
        ->get();

    expect($enrollment->ContactID)->toBe($contactId)
        ->and($enrollment->WorkflowID)->toBe('WFL_001')
        ->and($enrollment->WorkflowVersionID)->toBe('WFLV_002')
        ->and($enrollment->CurrentStepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($stepLogs)->toHaveCount(1)
        ->and($stepLogs[0]->StepKey)->toBe('ENROLLMENT_CREATED')
        ->and($stepLogs[0]->StepStatusCode)->toBe('COMPLETED');
});

it('skips creating a second active-like enrollment for the same contact and workflow version', function () {
    $contactId = firstWorkflowTestContactId();

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])
        ->expectsOutputToContain('Enrollment skipped: Contact already has an active-like enrollment for this workflow version.')
        ->assertExitCode(0);

    $enrollments = WorkflowEnrollment::query()->get();

    expect($enrollments)->toHaveCount(1)
        ->and($enrollments[0]->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($enrollments[0]->CurrentStepKey)->toBe('AWAIT_EMAIL_SIGNAL');
});

it('processes an email click event, completes the workflow, preserves correlation, and queues the expected actions', function () {
    $contactId = firstWorkflowTestContactId();
    $enrollment = enrollEmailFirstWorkflow($this, $contactId);
    $correlationKey = 'CORR_TEST_RUNTIME_001';

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => $correlationKey,
    ])->assertExitCode(0);

    $this->artisan('workflow:process')
        ->expectsOutputToContain('PROCESSED')
        ->expectsOutputToContain('queued 2 action(s)')
        ->assertExitCode(0);

    $enrollment->refresh();

    $event = WorkflowEventInbox::query()->firstOrFail();

    $queuedActions = WorkflowActionQueue::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('ActionTypeCode')
        ->get();

    $stepLogs = WorkflowStepLog::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('OccurredAtUTC')
        ->get();

    expect($event->ProcessingStatusCode)->toBe('PROCESSED')
        ->and($event->CorrelationKey)->toBe($correlationKey)
        ->and($enrollment->CurrentStepKey)->toBe('COMPLETE')
        ->and($enrollment->EnrollmentStatusCode)->toBe('COMPLETED')
        ->and($enrollment->CompletedReasonCode)->toBe('WORKFLOW_COMPLETED')
        ->and($queuedActions)->toHaveCount(2)
        ->and($queuedActions->pluck('ActionTypeCode')->all())->toBe([
            'APPLY_LEAD_SCORE',
            'UPDATE_LEAD_SUMMARY',
        ])
        ->and($queuedActions->pluck('TargetTypeCode')->unique()->all())->toBe(['CONTACT'])
        ->and($queuedActions->pluck('TargetID')->unique()->all())->toBe([$contactId])
        ->and($queuedActions->pluck('CorrelationKey')->unique()->all())->toBe([$correlationKey])
        ->and($stepLogs)->toHaveCount(2)
        ->and($stepLogs[0]->StepKey)->toBe('ENROLLMENT_CREATED')
        ->and($stepLogs[1]->StepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($stepLogs[1]->StepStatusCode)->toBe('COMPLETED')
        ->and($stepLogs[1]->RelatedEventID)->toBe($event->EventID);

    expect($queuedActions[0]->PayloadJson['score_rule_code'] ?? null)->toBe('EMAIL_CLICK')
        ->and($queuedActions[1]->PayloadJson['summary_code'] ?? null)->toBe('EMAIL_CLICKED');
});

it('ignores an email click event from the wrong source, writes an ignored step log, and queues no actions', function () {
    $contactId = firstWorkflowTestContactId();
    $enrollment = enrollEmailFirstWorkflow($this, $contactId);

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'FORM_CAPTURE',
        '--correlationKey' => 'CORR_TEST_RUNTIME_002',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')
        ->expectsOutputToContain('IGNORED')
        ->assertExitCode(0);

    $enrollment->refresh();

    $event = WorkflowEventInbox::query()->firstOrFail();
    $queuedActions = WorkflowActionQueue::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->get();

    $stepLogs = WorkflowStepLog::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('OccurredAtUTC')
        ->get();

    expect($event->ProcessingStatusCode)->toBe('IGNORED')
        ->and($enrollment->CurrentStepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($queuedActions)->toHaveCount(0)
        ->and($stepLogs)->toHaveCount(2)
        ->and($stepLogs[1]->StepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($stepLogs[1]->StepStatusCode)->toBe('IGNORED')
        ->and($stepLogs[1]->RelatedEventID)->toBe($event->EventID);
});

it('ignores an email click event when the category is not accepted by the current step conditions', function () {
    $contactId = firstWorkflowTestContactId();
    $enrollment = enrollEmailFirstWorkflow($this, $contactId);

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--category' => 'CAMPAIGN_CONTEXT',
        '--correlationKey' => 'CORR_TEST_RUNTIME_003',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')
        ->expectsOutputToContain('IGNORED')
        ->assertExitCode(0);

    $enrollment->refresh();

    $event = WorkflowEventInbox::query()->firstOrFail();
    $queuedActions = WorkflowActionQueue::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->get();

    $stepLogs = WorkflowStepLog::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('OccurredAtUTC')
        ->get();

    expect($event->ProcessingStatusCode)->toBe('IGNORED')
        ->and($event->EventCategoryCode)->toBe('CAMPAIGN_CONTEXT')
        ->and($enrollment->CurrentStepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($queuedActions)->toHaveCount(0)
        ->and($stepLogs)->toHaveCount(2)
        ->and($stepLogs[1]->StepStatusCode)->toBe('IGNORED');
});
