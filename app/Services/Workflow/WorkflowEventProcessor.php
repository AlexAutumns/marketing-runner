<?php

namespace App\Services\Workflow;

use App\Models\WorkflowEnrollment;
use App\Models\WorkflowEventInbox;
use App\Models\WorkflowStepLog;
use App\Models\WorkflowVersion;
use Illuminate\Support\Str;
use Throwable;

class WorkflowEventProcessor
{
    public function processPendingEvents(): array
    {
        $processed = 0;
        $ignored = 0;
        $failed = 0;

        $events = WorkflowEventInbox::where('ProcessingStatusCode', 'PENDING')
            ->orderBy('OccurredAtUTC')
            ->get();

        foreach ($events as $event) {
            try {
                $result = $this->processEvent($event);

                if ($result === 'processed') {
                    $processed++;
                } elseif ($result === 'ignored') {
                    $ignored++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $event->update([
                    'ProcessingStatusCode' => 'FAILED',
                    'ProcessedAtUTC' => now(),
                    'ProcessingErrorMessage' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'ignored' => $ignored,
            'failed' => $failed,
        ];
    }

    protected function processEvent(WorkflowEventInbox $event): string
    {
        $enrollment = $this->resolveEnrollment($event);

        if (! $enrollment) {
            $event->update([
                'ProcessingStatusCode' => 'IGNORED',
                'ProcessedAtUTC' => now(),
                'ProcessingErrorMessage' => 'No matching enrollment found.',
            ]);

            return 'ignored';
        }

        $version = WorkflowVersion::find($enrollment->WorkflowVersionID);

        if (! $version) {
            $event->update([
                'ProcessingStatusCode' => 'FAILED',
                'ProcessedAtUTC' => now(),
                'ProcessingErrorMessage' => 'Workflow version not found.',
            ]);

            return 'failed';
        }

        $acceptedEventTypes = data_get($version->ConditionConfigJson, 'accepted_event_types', []);

        if (! in_array($event->EventTypeCode, $acceptedEventTypes, true)) {
            $this->writeStepLog(
                enrollment: $enrollment,
                stepKey: $enrollment->CurrentStepKey ?? 'UNKNOWN',
                stepTypeCode: 'EVENT_EVALUATION',
                stepStatusCode: 'IGNORED',
                relatedEventId: $event->EventID,
                message: 'Event was received but not accepted by current workflow version.',
                details: [
                    'event_type' => $event->EventTypeCode,
                    'accepted_event_types' => $acceptedEventTypes,
                ]
            );

            $event->update([
                'ProcessingStatusCode' => 'IGNORED',
                'ProcessedAtUTC' => now(),
            ]);

            return 'ignored';
        }

        $enrollment->update([
            'EnrollmentStatusCode' => 'COMPLETED',
            'CurrentStepKey' => 'COMPLETE',
            'CompletedReasonCode' => 'TEST_EVENT_PROCESSED',
            'LastEventID' => $event->EventID,
            'CompletedAtUTC' => now(),
        ]);

        $this->writeStepLog(
            enrollment: $enrollment,
            stepKey: 'AWAIT_SIGNAL',
            stepTypeCode: 'EVENT_EVALUATION',
            stepStatusCode: 'COMPLETED',
            relatedEventId: $event->EventID,
            message: 'Event accepted and workflow enrollment completed.',
            details: [
                'event_type' => $event->EventTypeCode,
                'completion_reason' => 'TEST_EVENT_PROCESSED',
            ]
        );

        $event->update([
            'WorkflowID' => $enrollment->WorkflowID,
            'WorkflowVersionID' => $enrollment->WorkflowVersionID,
            'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
            'ProcessingStatusCode' => 'PROCESSED',
            'ProcessedAtUTC' => now(),
        ]);

        return 'processed';
    }

    protected function resolveEnrollment(WorkflowEventInbox $event): ?WorkflowEnrollment
    {
        if ($event->WorkflowEnrollmentID) {
            return WorkflowEnrollment::find($event->WorkflowEnrollmentID);
        }

        return WorkflowEnrollment::where('ContactID', $event->ContactID)
            ->where('EnrollmentStatusCode', 'ACTIVE')
            ->first();
    }

    protected function writeStepLog(
        WorkflowEnrollment $enrollment,
        string $stepKey,
        string $stepTypeCode,
        string $stepStatusCode,
        ?string $relatedEventId,
        string $message,
        array $details = []
    ): void {
        WorkflowStepLog::create([
            'StepLogID' => 'STP_'.Str::upper(Str::random(8)),
            'EnrollmentID' => $enrollment->EnrollmentID,
            'WorkflowID' => $enrollment->WorkflowID,
            'WorkflowVersionID' => $enrollment->WorkflowVersionID,
            'StepKey' => $stepKey,
            'StepTypeCode' => $stepTypeCode,
            'StepStatusCode' => $stepStatusCode,
            'RelatedEventID' => $relatedEventId,
            'Message' => $message,
            'DetailsJson' => $details,
            'OccurredAtUTC' => now(),
        ]);
    }
}
