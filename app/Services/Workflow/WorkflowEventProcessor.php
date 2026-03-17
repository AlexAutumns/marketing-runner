<?php

namespace App\Services\Workflow;

use App\Models\WorkflowActionQueue;
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
                    'event_category' => $event->EventCategoryCode,
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
                $this->markEventFailed($event, $e->getMessage());

                $summary['details'][] = [
                    'event_id' => $event->EventID,
                    'event_type' => $event->EventTypeCode,
                    'event_category' => $event->EventCategoryCode,
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
        $context = $this->resolveProcessingContext($event);

        if (! $context['ok']) {
            return $context['result'];
        }

        $stepResolution = $this->resolveCurrentStepContext(
            version: $context['version'],
            enrollment: $context['enrollment'],
            event: $event
        );

        if (! $stepResolution['ok']) {
            return $stepResolution['result'];
        }

        $eventValidation = $this->validateEventAgainstCurrentStep(
            event: $event,
            enrollment: $context['enrollment'],
            currentStepKey: $stepResolution['current_step_key'],
            currentStep: $stepResolution['current_step']
        );

        if (! $eventValidation['ok']) {
            return $eventValidation['result'];
        }

        return $this->applyStepTransition(
            event: $event,
            enrollment: $context['enrollment'],
            version: $context['version'],
            currentStepKey: $stepResolution['current_step_key'],
            currentStep: $stepResolution['current_step'],
            stepGraph: $stepResolution['step_graph'],
            actionConfig: $stepResolution['action_config']
        );
    }

    protected function resolveProcessingContext(WorkflowEventInbox $event): array
    {
        $enrollment = $this->resolveEnrollment($event);

        if (! $enrollment) {
            $this->markEventIgnored(
                event: $event,
                message: 'No matching enrollment found.'
            );

            return [
                'ok' => false,
                'result' => $this->ignoredResult(
                    event: $event,
                    message: 'No matching active enrollment found.',
                    enrollmentId: null
                ),
            ];
        }

        $version = WorkflowVersion::find($enrollment->WorkflowVersionID);

        if (! $version) {
            $this->markEventFailed(
                event: $event,
                message: 'Workflow version not found.'
            );

            return [
                'ok' => false,
                'result' => $this->failedResult(
                    event: $event,
                    message: 'Workflow version not found.',
                    enrollmentId: $enrollment->EnrollmentID
                ),
            ];
        }

        return [
            'ok' => true,
            'enrollment' => $enrollment,
            'version' => $version,
        ];
    }

    protected function resolveCurrentStepContext(
        WorkflowVersion $version,
        WorkflowEnrollment $enrollment,
        WorkflowEventInbox $event
    ): array {
        $stepGraph = $version->StepGraphJson ?? [];
        $actionConfig = $version->ActionConfigJson ?? [];

        $currentStepKey = $enrollment->CurrentStepKey ?: $this->getInitialStepKey($stepGraph);
        $currentStep = $this->getStepDefinition($stepGraph, $currentStepKey);

        if (! $currentStep) {
            $this->markEventFailed(
                event: $event,
                message: 'Current workflow step definition could not be resolved.'
            );

            return [
                'ok' => false,
                'result' => $this->failedResult(
                    event: $event,
                    message: 'Current workflow step definition could not be resolved.',
                    enrollmentId: $enrollment->EnrollmentID
                ),
            ];
        }

        return [
            'ok' => true,
            'step_graph' => $stepGraph,
            'action_config' => $actionConfig,
            'current_step_key' => $currentStepKey,
            'current_step' => $currentStep,
        ];
    }

    protected function validateEventAgainstCurrentStep(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment,
        string $currentStepKey,
        array $currentStep
    ): array {
        $acceptedEvents = $currentStep['accepted_events'] ?? [];

        if (! in_array($event->EventTypeCode, $acceptedEvents, true)) {
            $this->writeStepLog(
                enrollment: $enrollment,
                stepKey: $currentStepKey,
                stepTypeCode: $currentStep['type'] ?? 'UNKNOWN',
                stepStatusCode: 'IGNORED',
                relatedEventId: $event->EventID,
                message: 'Event was received, classified, and ignored because the current workflow step does not accept this event type.',
                details: [
                    'event_type' => $event->EventTypeCode,
                    'event_category' => $event->EventCategoryCode,
                    'current_step' => $currentStepKey,
                    'accepted_categories' => $currentStep['accepted_categories'] ?? [],
                    'accepted_events' => $acceptedEvents,
                ]
            );

            $this->markEventProcessedAsIgnored(
                event: $event,
                enrollment: $enrollment
            );

            return [
                'ok' => false,
                'result' => $this->ignoredResult(
                    event: $event,
                    message: "Event type [{$event->EventTypeCode}] was ignored because it is not accepted for current step [{$currentStepKey}].",
                    enrollmentId: $enrollment->EnrollmentID
                ),
            ];
        }

        return ['ok' => true];
    }

    protected function applyStepTransition(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment,
        WorkflowVersion $version,
        string $currentStepKey,
        array $currentStep,
        array $stepGraph,
        array $actionConfig
    ): array {
        $nextStepKey = $currentStep['next'] ?? null;
        $nextStep = $this->getStepDefinition($stepGraph, $nextStepKey);
        $isTerminal = (bool) ($nextStep['terminal'] ?? false);

        if ($isTerminal) {
            return $this->applyTerminalTransition(
                event: $event,
                enrollment: $enrollment,
                currentStepKey: $currentStepKey,
                currentStep: $currentStep,
                nextStepKey: $nextStepKey,
                actionConfig: $actionConfig
            );
        }

        return $this->applyNonTerminalTransition(
            event: $event,
            enrollment: $enrollment,
            currentStepKey: $currentStepKey,
            currentStep: $currentStep,
            nextStepKey: $nextStepKey,
            actionConfig: $actionConfig
        );
    }

    protected function applyTerminalTransition(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment,
        string $currentStepKey,
        array $currentStep,
        ?string $nextStepKey,
        array $actionConfig
    ): array {
        $enrollment->update([
            'EnrollmentStatusCode' => 'COMPLETED',
            'CurrentStepKey' => $nextStepKey,
            'CompletedReasonCode' => 'STEP_GRAPH_TERMINAL_REACHED',
            'LastEventID' => $event->EventID,
            'CompletedAtUTC' => now(),
        ]);

        $queuedActionIds = $this->queueStepActions(
            enrollment: $enrollment,
            event: $event,
            actionConfig: $actionConfig,
            stepKey: $currentStepKey
        );

        $this->writeStepLog(
            enrollment: $enrollment,
            stepKey: $currentStepKey,
            stepTypeCode: $currentStep['type'] ?? 'UNKNOWN',
            stepStatusCode: 'COMPLETED',
            relatedEventId: $event->EventID,
            message: 'Event was accepted by the current workflow step, the workflow advanced through the step graph, and the enrollment reached a terminal step.',
            details: [
                'event_type' => $event->EventTypeCode,
                'event_category' => $event->EventCategoryCode,
                'current_step' => $currentStepKey,
                'next_step' => $nextStepKey,
                'terminal' => true,
                'queued_action_ids' => $queuedActionIds,
            ]
        );

        $this->markEventProcessed(
            event: $event,
            enrollment: $enrollment
        );

        return $this->processedResult(
            event: $event,
            message: "Event type [{$event->EventTypeCode}] was accepted for step [{$currentStepKey}]. The enrollment advanced to terminal step [{$nextStepKey}] and queued ".count($queuedActionIds).' action(s).',
            enrollmentId: $enrollment->EnrollmentID
        );
    }

    protected function applyNonTerminalTransition(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment,
        string $currentStepKey,
        array $currentStep,
        ?string $nextStepKey,
        array $actionConfig
    ): array {
        $enrollment->update([
            'CurrentStepKey' => $nextStepKey,
            'LastEventID' => $event->EventID,
            'LastActionAtUTC' => now(),
        ]);

        $queuedActionIds = $this->queueStepActions(
            enrollment: $enrollment,
            event: $event,
            actionConfig: $actionConfig,
            stepKey: $currentStepKey
        );

        $this->writeStepLog(
            enrollment: $enrollment,
            stepKey: $currentStepKey,
            stepTypeCode: $currentStep['type'] ?? 'UNKNOWN',
            stepStatusCode: 'COMPLETED',
            relatedEventId: $event->EventID,
            message: 'Event was accepted by the current workflow step and the workflow advanced to the next configured step in the step graph.',
            details: [
                'event_type' => $event->EventTypeCode,
                'event_category' => $event->EventCategoryCode,
                'current_step' => $currentStepKey,
                'next_step' => $nextStepKey,
                'terminal' => false,
                'queued_action_ids' => $queuedActionIds,
            ]
        );

        $this->markEventProcessed(
            event: $event,
            enrollment: $enrollment
        );

        return $this->processedResult(
            event: $event,
            message: "Event type [{$event->EventTypeCode}] was accepted for step [{$currentStepKey}]. The enrollment advanced to [{$nextStepKey}] and queued ".count($queuedActionIds).' action(s).',
            enrollmentId: $enrollment->EnrollmentID
        );
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

    protected function markEventFailed(WorkflowEventInbox $event, string $message): void
    {
        $event->update([
            'ProcessingStatusCode' => 'FAILED',
            'ProcessedAtUTC' => now(),
            'ProcessingErrorMessage' => $message,
        ]);
    }

    protected function markEventIgnored(WorkflowEventInbox $event, string $message): void
    {
        $event->update([
            'ProcessingStatusCode' => 'IGNORED',
            'ProcessedAtUTC' => now(),
            'ProcessingErrorMessage' => $message,
        ]);
    }

    protected function markEventProcessedAsIgnored(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment
    ): void {
        $event->update([
            'WorkflowID' => $enrollment->WorkflowID,
            'WorkflowVersionID' => $enrollment->WorkflowVersionID,
            'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
            'ProcessingStatusCode' => 'IGNORED',
            'ProcessedAtUTC' => now(),
        ]);
    }

    protected function markEventProcessed(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment
    ): void {
        $event->update([
            'WorkflowID' => $enrollment->WorkflowID,
            'WorkflowVersionID' => $enrollment->WorkflowVersionID,
            'WorkflowEnrollmentID' => $enrollment->EnrollmentID,
            'ProcessingStatusCode' => 'PROCESSED',
            'ProcessedAtUTC' => now(),
        ]);
    }

    protected function ignoredResult(
        WorkflowEventInbox $event,
        string $message,
        ?string $enrollmentId
    ): array {
        return [
            'status' => 'ignored',
            'message' => $message,
            'enrollment_id' => $enrollmentId,
        ];
    }

    protected function failedResult(
        WorkflowEventInbox $event,
        string $message,
        ?string $enrollmentId
    ): array {
        return [
            'status' => 'failed',
            'message' => $message,
            'enrollment_id' => $enrollmentId,
        ];
    }

    protected function processedResult(
        WorkflowEventInbox $event,
        string $message,
        ?string $enrollmentId
    ): array {
        return [
            'status' => 'processed',
            'message' => $message,
            'enrollment_id' => $enrollmentId,
        ];
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

    protected function queueStepActions(
        WorkflowEnrollment $enrollment,
        WorkflowEventInbox $event,
        array $actionConfig,
        string $stepKey
    ): array {
        $queuedActionIds = [];

        $actions = data_get($actionConfig, "on_step_completion.{$stepKey}", []);

        foreach ($actions as $action) {
            $actionQueueId = 'ACTQ_'.Str::upper(Str::random(8));

            WorkflowActionQueue::create([
                'ActionQueueID' => $actionQueueId,
                'EnrollmentID' => $enrollment->EnrollmentID,
                'WorkflowID' => $enrollment->WorkflowID,
                'WorkflowVersionID' => $enrollment->WorkflowVersionID,
                'ActionTypeCode' => $action['action_type'] ?? 'UNKNOWN',
                'ActionStatusCode' => 'PENDING',
                'TargetTypeCode' => $action['target_type'] ?? null,
                'TargetID' => $enrollment->ContactID,
                'RelatedEventID' => $event->EventID,
                'CorrelationKey' => $event->CorrelationKey,
                'PayloadJson' => $action['payload'] ?? [],
                'ScheduledForUTC' => now(),
            ]);

            $queuedActionIds[] = $actionQueueId;
        }

        return $queuedActionIds;
    }
}
