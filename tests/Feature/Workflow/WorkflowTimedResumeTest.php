<?php

use App\Models\WorkflowEnrollment;
use App\Models\WorkflowEventInbox;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

afterEach(function () {
    $this->travelBack();
});

it('moves the baseline workflow into waiting and sets the due timestamp', function () {
    $contactId = DB::table('contacts')->value('contact_id');
    $baseTime = Carbon::parse('2026-04-01 10:00:00');

    $this->travelTo($baseTime);

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_001',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_001',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => 'CORR_TEST_WAIT_001',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')->assertExitCode(0);

    $enrollment->refresh();

    expect($enrollment->CurrentStepKey)->toBe('WAIT_BEFORE_STRONGER_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('WAITING')
        ->and($enrollment->WaitingUntilUTC)->not->toBeNull()
        ->and($enrollment->WaitingUntilUTC->toDateTimeString())->toBe(
            $baseTime->copy()->addMinutes(20)->toDateTimeString()
        );
});

it('does not resume the workflow before the due time', function () {
    $contactId = DB::table('contacts')->value('contact_id');
    $baseTime = Carbon::parse('2026-04-01 10:00:00');

    $this->travelTo($baseTime);

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_001',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_001',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => 'CORR_TEST_WAIT_002',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')->assertExitCode(0);

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

it('resumes a due waiting workflow once and does not duplicate the same wait-point event', function () {
    $contactId = DB::table('contacts')->value('contact_id');
    $baseTime = Carbon::parse('2026-04-01 10:00:00');

    $this->travelTo($baseTime);

    $this->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_001',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();

    $this->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_001',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => 'CORR_TEST_WAIT_003',
    ])->assertExitCode(0);

    $this->artisan('workflow:process')->assertExitCode(0);

    $dueAsOf = $baseTime->copy()->addMinutes(21)->toDateTimeString();

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
    ])->assertExitCode(0);

    $enrollment->refresh();

    $resumeEventsAfterFirstRun = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($enrollment->CurrentStepKey)->toBe('AWAIT_STRONGER_SIGNAL')
        ->and($enrollment->EnrollmentStatusCode)->toBe('ACTIVE')
        ->and($resumeEventsAfterFirstRun)->toBe(1);

    $this->artisan('workflow:resume-waiting', [
        '--asOf' => $dueAsOf,
        '--limit' => 50,
    ])->assertExitCode(0);

    $resumeEventsAfterSecondRun = WorkflowEventInbox::query()
        ->where('WorkflowEnrollmentID', $enrollment->EnrollmentID)
        ->where('EventTypeCode', 'WAIT_TIMER_REACHED')
        ->count();

    expect($resumeEventsAfterSecondRun)->toBe(1);
});
