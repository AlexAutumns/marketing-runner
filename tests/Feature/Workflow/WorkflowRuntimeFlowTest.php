<?php

use App\Models\WorkflowActionQueue;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowEventInbox;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('creates an active enrollment at the configured initial step', function () {
    $contactId = DB::table('contacts')->value('contact_id');

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->ContactID)->toBe($contactId)
        ->and($enrollment->WorkflowID)->toBe('WFL_001')
        ->and($enrollment->WorkflowVersionID)->toBe('WFLV_002')
        ->and($enrollment->CurrentStepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE');
});

it('processes an email click event and queues the expected actions', function () {
    $contactId = DB::table('contacts')->value('contact_id');

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => 'CORR_TEST_RUNTIME_001',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')->assertExitCode(0);

    $enrollment->refresh();

    $queuedActions = WorkflowActionQueue::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('ActionTypeCode')
        ->get();

    $event = WorkflowEventInbox::query()->firstOrFail();

    expect($event->ProcessingStatusCode)->toBe('PROCESSED')
        ->and($enrollment->CurrentStepKey)->toBe('COMPLETE')
        ->and($enrollment->EnrollmentStatusCode)->toBe('COMPLETED')
        ->and($enrollment->CompletedReasonCode)->toBe('WORKFLOW_COMPLETED')
        ->and($queuedActions)->toHaveCount(2)
        ->and($queuedActions->pluck('ActionTypeCode')->all())->toBe([
            'APPLY_LEAD_SCORE',
            'UPDATE_LEAD_SUMMARY',
        ])
        ->and($queuedActions->pluck('TargetTypeCode')->unique()->all())->toBe(['CONTACT'])
        ->and($queuedActions->pluck('TargetID')->unique()->all())->toBe([$contactId]);

    expect($queuedActions[0]->PayloadJson['score_rule_code'] ?? null)->toBe('EMAIL_CLICK');
    expect($queuedActions[1]->PayloadJson['summary_code'] ?? null)->toBe('EMAIL_CLICKED');
});

it('ignores an email click event from the wrong source and queues no actions', function () {
    $contactId = DB::table('contacts')->value('contact_id');

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'FORM_CAPTURE',
        '--correlationKey' => 'CORR_TEST_RUNTIME_002',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')->assertExitCode(0);

    $enrollment->refresh();

    $event = WorkflowEventInbox::query()->firstOrFail();
    $queuedActions = WorkflowActionQueue::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->get();

    expect($event->ProcessingStatusCode)->toBe('IGNORED')
        ->and($enrollment->CurrentStepKey)->toBe('AWAIT_EMAIL_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($queuedActions)->toHaveCount(0);
});
