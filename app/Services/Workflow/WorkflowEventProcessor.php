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
        $summary = [
            'total_pending' => 0,
            'processed' => 0,
            'ignored' => 0,
            'failed' => 0,
            'details' => [],
        ];

        $events = WorkflowEventInbox::where('ProcessingStatusCode', 'PENDING')
            ->orderBy('OccurredAtUTC')
            ->get();

        $summary['total_pending'] = $events->count();

        foreach ($events as $event) {
            try {
                $result = $this->processEvent($event);

                $summary['details'][] = [
                    'event_id' => $event->EventID,
                    'event_type' => $event->EventTypeCode,
                    'contact_id' => $event->ContactID,
                    'result' => $result['status'],
                    'message' => $result['message'],
                    'enrollment_id' => $result['enrollment_id'] ?? null,
                ];

                if ($result['status'] === 'processed') {
                    $summary['processed']++;
                } elseif ($result['status'] === 'ignored') {
                    $summary['ignored']++;
                } else {
                    $summary['failed']++;
                }
            } catch (Throwable $e) {
                $event->update([
                    'ProcessingStatusCode' => 'FAILED',
                    'ProcessedAtUTC' => now(),
                    'ProcessingErrorMessage' => $e->getMessage(),
                ]);

                $summary['details'][] = [
                    'event_id' => $event->EventID,
                    'event_type' => $event->EventTypeCode,
                    'contact_id' => $event->ContactID,
                    'result' => 'failed',
                    'message' => $e->getMessage(),
                    'enrollment_id' => null,
                ];

                $summary['failed']++;
            }
        }

        return $summary;
    }

    protected function processEvent(WorkflowEventInbox $event): array
    {
        $enrollment = $this->resolveEnrollment($event);

        if (! $enrollment) {
            $event->update([
                'ProcessingStatusCode' => 'IGNORED',
                'ProcessedAtUTC' => now(),
                'ProcessingErrorMessage' => 'No matching enrollment found.',
            ]);

            return [
                'status' => 'ignored',
                'message' => 'No matching active enrollment found.',
                'enrollment_id' => null,
            ];
        }

        $version = WorkflowVersion::find($enrollment->WorkflowVersionID);

        if (! $version) {
            $event->update([
                'ProcessingStatusCode' => 'FAILED',
                'ProcessedAtUTC' => now(),
                'ProcessingErrorMessage' => 'Workflow version not found.',
            ]);

            return [
                'status' => 'failed',
                'message' => 'Workflow version not found.',
                'enrollment_id' => $enrollment->EnrollmentID,
            ];
        }

        $stepGraph = $version->StepGraphJson ?? [];
        $currentStepKey = $enrollment->CurrentStepKey ?: $this->getInitialStepKey($stepGraph);
        $currentStep = $this->getStepDefinition($stepGraph, $currentStepKey);

        if (! $currentStep) {
            $event->update([
                'ProcessingStatusCode' => 'FAILED',
                'ProcessedAtUTC' => now(),
                'ProcessingErrorMessage' => 'Current workflow step definition could not be resolved.',
            ]);

            return [
                'status' => 'failed',
                'message' => 'Current workflow step definition could not be resolved.',
                'enrollment_id' => $enrollment->EnrollmentID,
            ];
        }

        $acceptedEvents = $currentStep['accepted_events'] ?? [];

        if (! in_array($event->EventTypeCode, $acceptedEvents, true)) {
            $this->writeStepLog(
                enrollment: $enrollment,
                stepKey: $currentStepKey ?? 'UNKNOWN',
                stepTypeCode: $currentStep['type'] ?? 'UNKNOWN',
                stepStatusCode: 'IGNORED',
                relatedEventId: $event->EventID,
                message: 'Event was received but not accepted by the current workflow step.',
                details: [
                    'event_type' => $event->EventTypeCode,
                    'current_step' => $currentStepKey,
                    'accepted_events' => $acceptedEvents,
                ]
            );

            $event->update([
                'WorkflowID' => $enrollment->WorkflowID,
                'WorkflowVersionID' => $enrollment->WorkflowVersionID,
                'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
                'ProcessingStatusCode' => 'IGNORED',
                'ProcessedAtUTC' => now(),
            ]);

            return [
                'status' => 'ignored',
                'message' => "Event type not accepted for current step [{$currentStepKey}].",
                'enrollment_id' => $enrollment->EnrollmentID,
            ];
        }

        $nextStepKey = $currentStep['next'] ?? null;
        $nextStep = $this->getStepDefinition($stepGraph, $nextStepKey);
        $isTerminal = (bool) ($nextStep['terminal'] ?? false);

        if ($isTerminal) {
            $enrollment->update([
                'EnrollmentStatusCode' => 'COMPLETED',
                'CurrentStepKey' => $nextStepKey,
                'CompletedReasonCode' => 'STEP_GRAPH_TERMINAL_REACHED',
                'LastEventID' => $event->EventID,
                'CompletedAtUTC' => now(),
            ]);

            $this->writeStepLog(
                enrollment: $enrollment,
                stepKey: $currentStepKey,
                stepTypeCode: $currentStep['type'] ?? 'UNKNOWN',
                stepStatusCode: 'COMPLETED',
                relatedEventId: $event->EventID,
                message: 'Event accepted and workflow reached a terminal step.',
                details: [
                    'event_type' => $event->EventTypeCode,
                    'current_step' => $currentStepKey,
                    'next_step' => $nextStepKey,
                    'terminal' => true,
                ]
            );

            $event->update([
                'WorkflowID' => $enrollment->WorkflowID,
                'WorkflowVersionID' => $enrollment->WorkflowVersionID,
                'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
                'ProcessingStatusCode' => 'PROCESSED',
                'ProcessedAtUTC' => now(),
            ]);

            return [
                'status' => 'processed',
                'message' => "Event accepted. Enrollment advanced from [{$currentStepKey}] to terminal step [{$nextStepKey}].",
                'enrollment_id' => $enrollment->EnrollmentID,
            ];
        }

        $enrollment->update([
            'CurrentStepKey' => $nextStepKey,
            'LastEventID' => $event->EventID,
            'LastActionAtUTC' => now(),
        ]);

        $this->writeStepLog(
            enrollment: $enrollment,
            stepKey: $currentStepKey,
            stepTypeCode: $currentStep['type'] ?? 'UNKNOWN',
            stepStatusCode: 'COMPLETED',
            relatedEventId: $event->EventID,
            message: 'Event accepted and workflow advanced to the next step.',
            details: [
                'event_type' => $event->EventTypeCode,
                'current_step' => $currentStepKey,
                'next_step' => $nextStepKey,
                'terminal' => false,
            ]
        );

        $event->update([
            'WorkflowID' => $enrollment->WorkflowID,
            'WorkflowVersionID' => $enrollment->WorkflowVersionID,
            'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
            'ProcessingStatusCode' => 'PROCESSED',
            'ProcessedAtUTC' => now(),
        ]);

        return [
            'status' => 'processed',
            'message' => "Event accepted. Enrollment advanced from [{$currentStepKey}] to [{$nextStepKey}].",
            'enrollment_id' => $enrollment->EnrollmentID,
        ];
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

    protected function getInitialStepKey(array $stepGraph): ?string
    {
        return $stepGraph['initial_step'] ?? null;
    }
}
