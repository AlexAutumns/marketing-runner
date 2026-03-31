<?php

use App\Models\WorkflowEventInbox;
use App\Models\WorkflowStepLog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

afterEach(function () {
    $this->travelBack();
});

it('moves the baseline workflow into waiting, writes the waiting logs, and sets the due timestamp from the wait config', function () {
    $baseTime = Carbon::parse('2026-04-01 10:00:00');
    $this->travelTo($baseTime);

    $enrollment = moveBaselineWorkflowIntoWaiting(
        testCase: $this,
        contactId: firstWorkflowTestContactId(),
        correlationKey: 'CORR_TEST_WAIT_001'
    );

    $stepLogs = WorkflowStepLog::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('OccurredAtUTC')
        ->get();

    expect($enrollment->CurrentStepKey)->toBe('WAIT_BEFORE_STRONGER_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('WAITING')
        ->and($enrollment->WaitingUntilUTC)->not->toBeNull()
        ->and($enrollment->WaitingUntilUTC->toDateTimeString())->toBe(
            $baseTime->copy()->addMinutes(20)->toDateTimeString()
        )
        ->and($stepLogs)->toHaveCount(3)
        ->and($stepLogs[0]->StepKey)->toBe('ENROLLMENT_CREATED')
        ->and($stepLogs[1]->StepKey)->toBe('AWAIT_INITIAL_ENGAGEMENT')
        ->and($stepLogs[1]->StepStatusCode)->toBe('COMPLETED')
        ->and($stepLogs[2]->StepKey)->toBe('WAIT_BEFORE_STRONGER_SIGNAL')
        ->and($stepLogs[2]->StepStatusCode)->toBe('WAITING');
});

it('does not create due events or resume the workflow when resume-waiting is run in dry-run mode', function () {
    $baseTime = Carbon::parse('2026-04-01 10:00:00');
    $this->travelTo($baseTime);

    $enrollment = moveBaselineWorkflowIntoWaiting(
        testCase: $this,
        contactId: firstWorkflowTestContactId(),
        correlationKey: 'CORR_TEST_WAIT_002'
    );

    $dueAsOf = $baseTime->copy()->addMinutes(21)->toDateTimeString();

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run completed')
        ->assertExitCode(0);

    $enrollment->refresh();

    $resumeEvents = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($enrollment->CurrentStepKey)->toBe('WAIT_BEFORE_STRONGER_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('WAITING')
        ->and($resumeEvents)->toBe(0);
});

it('does not resume the workflow before the due time', function () {
    $baseTime = Carbon::parse('2026-04-01 10:00:00');
    $this->travelTo($baseTime);

    $enrollment = moveBaselineWorkflowIntoWaiting(
        testCase: $this,
        contactId: firstWorkflowTestContactId(),
        correlationKey: 'CORR_TEST_WAIT_003'
    );

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $baseTime->copy()->addMinutes(19)->toDateTimeString(),
        '--limit' => 50,
    ])->assertExitCode(0);

    $enrollment->refresh();

    $resumeEvents = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($enrollment->CurrentStepKey)->toBe('WAIT_BEFORE_STRONGER_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('WAITING')
        ->and($resumeEvents)->toBe(0);
});

it('rejects a non-positive resume limit before doing any due-work inspection', function () {
    $this->artisan('workflow:resume-waiting', [
        '--limit' => 0,
    ])
        ->expectsOutputToContain('Validation failed: --limit must be greater than 0.')
        ->assertExitCode(1);
});

it('rejects an invalid as-of datetime before doing any due-work inspection', function () {
    $this->artisan('workflow:resume-waiting', [
        '--asOf' => 'NOT_A_REAL_DATETIME',
        '--limit' => 50,
    ])
        ->expectsOutputToContain('Validation failed: --asOf must be a valid datetime string.')
        ->assertExitCode(1);
});

it('resumes a due waiting workflow, processes the due event, preserves the dedupe key shape, and does not duplicate the same wait-point event on rerun', function () {
    $baseTime = Carbon::parse('2026-04-01 10:00:00');
    $this->travelTo($baseTime);

    $enrollment = moveBaselineWorkflowIntoWaiting(
        testCase: $this,
        contactId: firstWorkflowTestContactId(),
        correlationKey: 'CORR_TEST_WAIT_004'
    );

    $expectedWaitingUntil = $baseTime->copy()->addMinutes(20);
    $expectedDedupeKey = 'WAIT_RESUME:'.$enrollment->EnrollmentID.':'.$expectedWaitingUntil->timestamp;

    $dueAsOf = $baseTime->copy()->addMinutes(21)->toDateTimeString();

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
    ])
        ->expectsOutputToContain('Created WAIT_TIMER_REACHED event')
        ->expectsOutputToContain('Processed               : 1')
        ->assertExitCode(0);

    $enrollment->refresh();

    $resumeEvents = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->orderBy('OccurredAtUTC')
        ->get();

    $stepLogs = WorkflowStepLog::query()
        ->where('EnrollmentID', $enrollment->EnrollmentID)
        ->orderBy('OccurredAtUTC')
        ->get();

    expect($enrollment->CurrentStepKey)->toBe('AWAIT_STRONGER_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($resumeEvents)->toHaveCount(1)
        ->and($resumeEvents[0]->ProcessingStatusCode)->toBe('PROCESSED')
        ->and($resumeEvents[0]->EventSourceCode)->toBe('SYSTEM_RESUME')
        ->and($resumeEvents[0]->DedupeKey)->toBe($expectedDedupeKey)
        ->and($resumeEvents[0]->PayloadJson['resume_reason'])->toBe('WAIT_STEP_DUE')
        ->and($resumeEvents[0]->PayloadJson['waiting_until_utc'])->toBe($expectedWaitingUntil->toDateTimeString())
        ->and($resumeEvents[0]->PayloadJson['resume_checked_as_of_utc'])->toBe($baseTime->copy()->addMinutes(21)->toDateTimeString())
        ->and($stepLogs)->toHaveCount(4)
        ->and($stepLogs[3]->StepKey)->toBe('WAIT_BEFORE_STRONGER_SIGNAL')
        ->and($stepLogs[3]->StepStatusCode)->toBe('COMPLETED')
        ->and($stepLogs[3]->RelatedEventID)->toBe($resumeEvents[0]->EventID);

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
    ])->assertExitCode(0);

    $resumeEventCountAfterSecondRun = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($resumeEventCountAfterSecondRun)->toBe(1);
});

it('skips a stale waiting enrollment when the current step is no longer a wait step', function () {
    $baseTime = Carbon::parse('2026-04-01 10:00:00');
    $this->travelTo($baseTime);

    $enrollment = moveBaselineWorkflowIntoWaiting(
        testCase: $this,
        contactId: firstWorkflowTestContactId(),
        correlationKey: 'CORR_TEST_WAIT_005'
    );

    $enrollment->update([
        'CurrentStepKey' => 'AWAIT_INITIAL_ENGAGEMENT',
        'EnrollmentStatusCode' => 'WAITING',
        'WaitingUntilUTC' => $baseTime->copy()->addMinutes(20),
    ]);

    $dueAsOf = $baseTime->copy()->addMinutes(21)->toDateTimeString();

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
    ])
        ->expectsOutputToContain('current_step_not_wait_for_time')
        ->assertExitCode(0);

    $resumeEvents = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($resumeEvents)->toBe(0);
});

it('skips a waiting enrollment that no longer has a waiting-until timestamp', function () {
    $baseTime = Carbon::parse('2026-04-01 10:00:00');
    $this->travelTo($baseTime);

    $enrollment = moveBaselineWorkflowIntoWaiting(
        testCase: $this,
        contactId: firstWorkflowTestContactId(),
        correlationKey: 'CORR_TEST_WAIT_006'
    );

    $enrollment->update([
        'EnrollmentStatusCode' => 'WAITING',
        'WaitingUntilUTC' => null,
    ]);

    $dueAsOf = $baseTime->copy()->addMinutes(21)->toDateTimeString();

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
    ])
        ->expectsOutputToContain('No due waiting enrollments were found for the current resume window.')
        ->assertExitCode(0);

    $resumeEvents = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($resumeEvents)->toBe(0);
});
