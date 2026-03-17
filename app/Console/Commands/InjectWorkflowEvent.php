<?php

namespace App\Console\Commands;

use App\Models\WorkflowEventInbox;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InjectWorkflowEvent extends Command
{
    protected $signature = 'workflow:event
                            {eventType : Event type code}
                            {contactId : Contact identifier}
                            {--workflowId= : Optional workflow identifier}
                            {--workflowVersionId= : Optional workflow version identifier}
                            {--enrollmentId= : Optional workflow enrollment identifier}
                            {--companyId= : Optional company identifier}
                            {--source=MANUAL_TEST : Event source code}
                            {--category= : Optional event category code; inferred automatically if omitted}
                            {--correlationKey= : Optional correlation key}';

    protected $description = 'Inject a workflow event into the event inbox';

    /**
     * Stable event categories currently supported by the workflow-kernel branch.
     *
     * These are intentionally broad so the workflow core stays resilient even if
     * surrounding apps and teams adjust their exact event-type vocabulary later.
     */
    private const ALLOWED_EVENT_CATEGORIES = [
        'ENGAGEMENT',
        'CAMPAIGN_CONTEXT',
        'WORKFLOW_CONTROL',
    ];

    /**
     * Known event types currently supported in the sample workflow/demo flow.
     *
     * This list is intentionally small and explicit for now to keep the event
     * contract readable and controlled during early integration work.
     */
    private const ALLOWED_EVENT_TYPES = [
        'MANUAL_TEST_EVENT',
        'EMAIL_LINK_CLICKED',
        'BROCHURE_LINK_CLICKED',
        'FORM_SUBMITTED',
        'CAMPAIGN_READY',
        'ASSET_APPROVED',
    ];

    public function handle(): int
    {

        $eventId = 'EVT_'.Str::upper(Str::random(8));

        $eventType = strtoupper(trim($this->argument('eventType')));
        $contactId = $this->argument('contactId');
        $workflowId = $this->option('workflowId');
        $workflowVersionId = $this->option('workflowVersionId');
        $enrollmentId = $this->option('enrollmentId');
        $companyId = $this->option('companyId');
        $source = strtoupper(trim((string) $this->option('source')));
        $rawCategory = strtoupper(trim((string) $this->option('category')));
        $correlationKey = $this->option('correlationKey');

        $category = $rawCategory !== ''
            ? $rawCategory
            : $this->inferDefaultCategory($eventType);

        $this->newLine();
        $this->info('WORKFLOW EVENT INJECTION');
        $this->line(str_repeat('-', 60));
        $this->line("Input EventTypeCode     : {$eventType}");
        $this->line("Input ContactID         : {$contactId}");
        $this->line('Input WorkflowID        : '.($workflowId ?: '[none]'));
        $this->line('Input WorkflowVersionID : '.($workflowVersionId ?: '[none]'));
        $this->line('Input EnrollmentID      : '.($enrollmentId ?: '[none]'));
        $this->line('Input CompanyID         : '.($companyId ?: '[none]'));
        $this->line("Input EventSourceCode   : {$source}");
        $this->line("Input EventCategoryCode : {$category}");
        $this->line('Input CorrelationKey    : '.($correlationKey ?: '[none]'));
        $this->line(str_repeat('-', 60));

        if (! $this->isAllowedCategory($category)) {
            $this->error("Validation failed: EventCategoryCode [{$category}] is not currently supported.");
            $this->line('Allowed categories: '.implode(', ', self::ALLOWED_EVENT_CATEGORIES));

            return self::FAILURE;
        }

        if (! $this->isAllowedEventType($eventType)) {
            $this->warn("EventTypeCode [{$eventType}] is not in the current known event-type list.");
            $this->line('Known event types: '.implode(', ', self::ALLOWED_EVENT_TYPES));
            $this->line('The event will still be accepted for flexibility, but it may be ignored by the current workflow version.');
        }

        $event = WorkflowEventInbox::create([
            'EventID' => $eventId,
            'EventTypeCode' => $eventType,
            'EventCategoryCode' => $category,
            'EventSourceCode' => $source,
            'ContactID' => $contactId,
            'CompanyID' => $companyId,
            'WorkflowID' => $workflowId,
            'WorkflowVersionID' => $workflowVersionId,
            'WorkflowEnrollmentID' => $enrollmentId,
            'CorrelationKey' => $correlationKey,
            'OccurredAtUTC' => now(),
            'PayloadJson' => [
                'injected_by' => 'workflow:event command',
                'notes' => 'Manual event injection for workflow-kernel development',
            ],
            'ProcessingStatusCode' => 'PENDING',
        ]);

        $this->info('Workflow event created successfully.');
        $this->line("EventID                 : {$event->EventID}");
        $this->line("Stored ProcessingStatus : {$event->ProcessingStatusCode}");
        $this->line('OccurredAtUTC           : '.$event->OccurredAtUTC?->toDateTimeString());
        $this->line("Stored EventCategory    : {$event->EventCategoryCode}");

        $this->line(str_repeat('-', 60));
        $this->comment('Next suggested step: process pending workflow events');
        $this->comment('Example: php artisan workflow:process');
        $this->line(str_repeat('-', 60));

        return self::SUCCESS;
    }

    protected function inferDefaultCategory(string $eventType): string
    {
        return match ($eventType) {
            'EMAIL_LINK_CLICKED', 'BROCHURE_LINK_CLICKED', 'FORM_SUBMITTED' => 'ENGAGEMENT',
            'CAMPAIGN_READY', 'ASSET_APPROVED' => 'CAMPAIGN_CONTEXT',
            default => 'WORKFLOW_CONTROL',
        };
    }

    protected function isAllowedCategory(string $category): bool
    {
        return in_array($category, self::ALLOWED_EVENT_CATEGORIES, true);
    }

    protected function isAllowedEventType(string $eventType): bool
    {
        return in_array($eventType, self::ALLOWED_EVENT_TYPES, true);
    }
}
