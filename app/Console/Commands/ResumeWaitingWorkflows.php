<?php

namespace App\Console\Commands;

use App\Models\WorkflowEnrollment;
use App\Models\WorkflowEventInbox;
use App\Services\Workflow\WorkflowEventProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResumeWaitingWorkflows extends Command
{
    protected $signature = 'workflow:resume-waiting
                        {--asOf= : Optional UTC timestamp override for due-check testing}
                        {--limit=50 : Maximum number of due waiting enrollments to inspect in one run}
                        {--dry-run : Show which due waiting enrollments would be resumed without creating events}';

    protected $description = 'Resume due waiting workflow enrollments by generating internal due events and processing them';

    public function handle(WorkflowEventProcessor $processor): int
    {
        $asOfInput = $this->option('asOf');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($limit <= 0) {
            $this->error('Validation failed: --limit must be greater than 0.');

            return self::FAILURE;
        }

        try {
            $asOf = $asOfInput ? Carbon::parse($asOfInput) : now();
        } catch (\Throwable $e) {
            $this->error('Validation failed: --asOf must be a valid datetime string.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('WORKFLOW WAITING RESUME COMMAND');
        $this->line(str_repeat('-', 70));
        $this->line('AsOf UTC : '.$asOf->toDateTimeString());
        $this->line('Limit    : '.$limit);
        $this->line('Dry Run  : '.($dryRun ? 'yes' : 'no'));
        $this->line(str_repeat('-', 70));

        $candidates = WorkflowEnrollment::query()
            ->where('EnrollmentStatusCode', 'WAITING')
            ->whereNotNull('WaitingUntilUTC')
            ->where('WaitingUntilUTC', '<=', $asOf)
            ->orderBy('WaitingUntilUTC')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->comment('No due waiting enrollments were found.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        $createdEvents = 0;
        $skipCounts = [
            'enrollment_missing' => 0,
            'status_not_waiting' => 0,
            'waiting_until_missing' => 0,
            'not_due_yet' => 0,
            'current_step_unresolved' => 0,
            'current_step_not_wait_for_time' => 0,
            'duplicate_resume_event' => 0,
        ];
        $createdEventIds = [];

        foreach ($candidates as $candidate) {
            $enrollment = WorkflowEnrollment::find($candidate->EnrollmentID);

            $eligibility = $this->evaluateResumeEligibility($enrollment, $asOf);

            if (! $eligibility['eligible']) {
                $reason = $eligibility['reason'] ?? 'unknown';

                if (! array_key_exists($reason, $skipCounts)) {
                    $skipCounts[$reason] = 0;
                }

                $skipCounts[$reason]++;

                $this->warn("Skipped EnrollmentID {$candidate->EnrollmentID} because resume eligibility failed [{$reason}].");

                continue;
            }

            $dedupeKey = 'WAIT_RESUME:'.$enrollment->EnrollmentID.':'.optional($enrollment->WaitingUntilUTC)->timestamp;

            $alreadyExists = WorkflowEventInbox::query()
                ->where('DedupeKey', $dedupeKey)
                ->whereIn('ProcessingStatusCode', ['PENDING', 'PROCESSED'])
                ->exists();

            if ($alreadyExists) {
                $skipCounts['duplicate_resume_event']++;

                $this->warn("Skipped EnrollmentID {$enrollment->EnrollmentID} because a matching resume event already exists.");

                continue;
            }

            if ($dryRun) {
                $this->line(
                    "Would create WAIT_TIMER_REACHED for EnrollmentID {$enrollment->EnrollmentID} "
                    ."(ContactID {$enrollment->ContactID}) "
                    .'due at '.optional($enrollment->WaitingUntilUTC)->toDateTimeString()
                );

                continue;
            }

            $eventId = 'EVT_'.Str::upper(Str::random(8));

            WorkflowEventInbox::create([
                'EventID' => $eventId,
                'EventTypeCode' => 'WAIT_TIMER_REACHED',
                'EventCategoryCode' => 'WORKFLOW_CONTROL',
                'EventSourceCode' => 'SYSTEM_RESUME',
                'ContactID' => $enrollment->ContactID,
                'CompanyID' => $enrollment->CompanyID,
                'WorkflowID' => $enrollment->WorkflowID,
                'WorkflowVersionID' => $enrollment->WorkflowVersionID,
                'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
                'CorrelationKey' => 'WAITING_RESUME_'.$enrollment->EnrollmentID,
                'DedupeKey' => $dedupeKey,
                'OccurredAtUTC' => $asOf,
                'PayloadJson' => [
                    'resume_reason' => 'WAIT_STEP_DUE',
                    'waiting_until_utc' => optional($enrollment->WaitingUntilUTC)->toDateTimeString(),
                    'resume_checked_as_of_utc' => $asOf->toDateTimeString(),
                ],
                'ProcessingStatusCode' => 'PENDING',
            ]);

            $createdEventIds[] = $eventId;

            $createdEvents++;

            $this->line("Created WAIT_TIMER_REACHED event {$eventId} for EnrollmentID {$enrollment->EnrollmentID}");
        }

        $this->line(str_repeat('-', 70));
        $this->line("Created due events                 : {$createdEvents}");
        $this->line("Skipped (enrollment missing)       : {$skipCounts['enrollment_missing']}");
        $this->line("Skipped (status not waiting)       : {$skipCounts['status_not_waiting']}");
        $this->line("Skipped (waiting until missing)    : {$skipCounts['waiting_until_missing']}");
        $this->line("Skipped (not due yet)              : {$skipCounts['not_due_yet']}");
        $this->line("Skipped (step unresolved)          : {$skipCounts['current_step_unresolved']}");
        $this->line("Skipped (step not wait_for_time)   : {$skipCounts['current_step_not_wait_for_time']}");
        $this->line("Skipped (duplicate resume event)   : {$skipCounts['duplicate_resume_event']}");
        $this->line(str_repeat('-', 70));

        if ($dryRun) {
            $this->comment('Dry run completed. No resume events were created and no workflow processing was executed.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        if ($createdEvents === 0) {
            $this->comment('No new due events were created, so workflow processing was not run.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        $this->comment('Running workflow:process logic for pending events...');
        $this->line(str_repeat('-', 70));

        $result = $processor->processPendingEventsByIds($createdEventIds);

        $this->line('Pending workflow events : '.$result['total_pending']);
        $this->line('Processed               : '.$result['processed']);
        $this->line('Ignored                 : '.$result['ignored']);
        $this->line('Failed                  : '.$result['failed']);
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }

    protected function evaluateResumeEligibility(?WorkflowEnrollment $enrollment, Carbon $asOf): array
    {
        if (! $enrollment) {
            return [
                'eligible' => false,
                'reason' => 'enrollment_missing',
            ];
        }

        if ($enrollment->EnrollmentStatusCode !== 'WAITING') {
            return [
                'eligible' => false,
                'reason' => 'status_not_waiting',
            ];
        }

        if (! $enrollment->WaitingUntilUTC) {
            return [
                'eligible' => false,
                'reason' => 'waiting_until_missing',
            ];
        }

        if ($enrollment->WaitingUntilUTC->gt($asOf)) {
            return [
                'eligible' => false,
                'reason' => 'not_due_yet',
            ];
        }

        $version = $enrollment->version;
        $stepGraph = $version?->StepGraphJson ?? [];
        $currentStep = $this->getStepDefinition($stepGraph, $enrollment->CurrentStepKey);

        if (! $currentStep) {
            return [
                'eligible' => false,
                'reason' => 'current_step_unresolved',
            ];
        }

        if (($currentStep['type'] ?? null) !== 'WAIT_FOR_TIME') {
            return [
                'eligible' => false,
                'reason' => 'current_step_not_wait_for_time',
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'eligible',
        ];
    }

    protected function getStepDefinition(array $stepGraph, ?string $stepKey): ?array
    {
        if (! $stepKey) {
            return null;
        }

        $steps = $stepGraph['steps'] ?? [];

        foreach ($steps as $step) {
            if (($step['key'] ?? null) === $stepKey) {
                return $step;
            }
        }

        return null;
    }
}
