<?php

namespace App\Services\Workflow;

use App\Models\WorkflowActionQueue;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowEventInbox;
use App\Models\WorkflowStepLog;
use App\Models\WorkflowVersion;
use Illuminate\Support\Str;
use Throwable;

/**
 * WorkflowEventProcessor is the workflow-kernel orchestration service.
 *
 * It is intentionally responsible for coordinating workflow event handling,
 * not for owning every surrounding domain in the CRM.
 *
 * Its main responsibilities are:
 * - read pending workflow-facing events
 * - resolve workflow runtime context
 * - interpret an event against the current workflow step
 * - move workflow state forward through the step graph
 * - write workflow history
 * - queue follow-up workflow actions
 *
 * Important design boundary:
 * - this class interprets and orchestrates workflow behavior
 * - it does not execute provider actions directly
 * - it does not build campaigns
 * - it does not own external scoring logic
 *
 * Keep the core logic here durable and readable.
 * Flexible upstream/downstream integrations should connect through:
 * - workflow event inbox (inputs)
 * - workflow action queue (outputs)
 */
class WorkflowEventProcessor
{
    /**
     * Process all currently pending workflow events in arrival order.
     *
     * This method is intentionally kept as the batch coordinator:
     * - it loads the pending inbox rows
     * - it delegates one-event handling to processEvent()
     * - it aggregates operator-friendly summary output
     *
     * The detailed workflow decision-making should stay below this layer.
     * This helps keep batch processing readable and easier to support.
     */
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

    /**
     * Process one workflow-facing event through the workflow kernel.
     *
     * This method is intentionally written as an orchestrator, not as one large
     * block of mixed logic. Its flow is:
     * 1. resolve processing context
     * 2. resolve current workflow step context
     * 3. validate the event against the current step
     * 4. apply the step transition
     *
     * Keeping this method high-level makes the workflow path easier to understand,
     * review, and extend without silently breaking the core behavior.
     */
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
            currentStepKey: $stepResolution['current_step_key'],
            currentStep: $stepResolution['current_step'],
            stepGraph: $stepResolution['step_graph'],
            actionConfig: $stepResolution['action_config']
        );
    }

    /**
     * Resolve the minimum workflow runtime context needed to process an event.
     *
     * Current required context:
     * - the matching workflow enrollment/run
     * - the workflow version used by that enrollment
     *
     * If either cannot be resolved, processing stops early and the event is marked
     * as ignored or failed, depending on the reason.
     *
     * This method exists to keep context-resolution failures separate from
     * step-interpretation logic.
     */
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

    /**
     * Resolve the current workflow step context from the workflow version and
     * the enrollment's current runtime position.
     *
     * This method loads:
     * - the step graph
     * - the action configuration
     * - the current step key
     * - the current step definition
     *
     * The processor depends on this step-aware context so that workflow behavior
     * is driven by the stored workflow version, not by hardcoded branching rules.
     */
    protected function resolveCurrentStepContext(
        WorkflowVersion $version,
        WorkflowEnrollment $enrollment,
        WorkflowEventInbox $event
    ): array {
        // The workflow version is the source of truth for step flow and action intent.
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

    /**
     * Check whether the current workflow step accepts the incoming event.
     *
     * Important design note:
     * - event categories are intentionally broad and stable
     * - event types are more specific and are used for current step matching
     *
     * At this stage, the processor matches by accepted event types, not by
     * category alone. That is intentional:
     * category helps keep the event model structured,
     * while event type keeps the workflow behavior precise.
     *
     * This balance supports flexible edges without making step behavior too vague.
     */
    protected function validateEventAgainstCurrentStep(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment,
        string $currentStepKey,
        array $currentStep
    ): array {
        // Categories keep the event model structured; step matching still stays event-type specific.
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
                    message: "Event type [{$event->EventTypeCode}] was ignored because it is not accepted for current step [{$currentStepKey}].",
                    enrollmentId: $enrollment->EnrollmentID
                ),
            ];
        }

        return ['ok' => true];
    }

    /**
     * Apply the workflow transition after an event has been accepted for the
     * current step.
     *
     * This method decides whether the workflow advances into:
     * - a terminal step (completing the enrollment), or
     * - a non-terminal step (continuing the workflow run)
     *
     * The actual update behavior is split into dedicated terminal and
     * non-terminal handlers to keep each transition path readable.
     */
    protected function applyStepTransition(
        WorkflowEventInbox $event,
        WorkflowEnrollment $enrollment,
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

    /**
     * Apply a terminal workflow transition.
     *
     * Terminal transitions:
     * - complete the workflow enrollment/run
     * - write a step log explaining the transition
     * - queue any configured follow-up actions for the completed step
     * - mark the event as processed in workflow context
     *
     * Even when a workflow run completes, action queue output stays separate from
     * action execution. This preserves the boundary between workflow decision and
     * external execution.
     */
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
            message: "Event type [{$event->EventTypeCode}] was accepted for step [{$currentStepKey}]. The enrollment advanced to terminal step [{$nextStepKey}] and queued ".count($queuedActionIds).' action(s).',
            enrollmentId: $enrollment->EnrollmentID
        );
    }

    /**
     * Apply a non-terminal workflow transition.
     *
     * Non-terminal transitions:
     * - move the enrollment/run to the next workflow step
     * - keep the workflow run active
     * - write step history
     * - queue any configured follow-up actions for the completed step
     * - mark the event as processed in workflow context
     *
     * This keeps step progression and follow-up intent explicit while preserving
     * the current state/history/action separation in the workflow kernel.
     */
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

    /**
     * Mark an event as failed when processing cannot continue safely.
     */
    protected function markEventFailed(WorkflowEventInbox $event, string $message): void
    {
        $event->update([
            'ProcessingStatusCode' => 'FAILED',
            'ProcessedAtUTC' => now(),
            'ProcessingErrorMessage' => $message,
        ]);
    }

    /**
     * Mark an event as ignored when it is valid enough to record, but not usable
     * for the current workflow processing path.
     */
    protected function markEventIgnored(WorkflowEventInbox $event, string $message): void
    {
        $event->update([
            'ProcessingStatusCode' => 'IGNORED',
            'ProcessedAtUTC' => now(),
            'ProcessingErrorMessage' => $message,
        ]);
    }

    /**
     * Mark an event as ignored while still attaching resolved workflow context.
     *
     * This is useful when the event reached a valid workflow run, but the current
     * step chose not to act on that event type.
     */
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

    /**
     * Mark an event as successfully processed and attach the resolved workflow
     * context used during processing.
     */
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

    /**
     * Find one step definition inside the stored workflow step graph by key.
     */
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

    /**
     * Return the configured initial step key from the stored workflow step graph.
     */
    protected function getInitialStepKey(array $stepGraph): ?string
    {
        return $stepGraph['initial_step'] ?? null;
    }

    /**
     * Queue workflow-configured actions for a completed step.
     *
     * Important design boundary:
     * this method records action intent only.
     * It does not execute provider calls or external side effects directly.
     *
     * Keeping action intent in the workflow action queue makes the workflow core
     * easier to test, safer to evolve, and easier to integrate with other teams'
     * execution paths later.
     */
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
