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
                            {--category=TEST : Event category code}
                            {--correlationKey= : Optional correlation key}';

    protected $description = 'Inject a workflow event into the event inbox';

    public function handle(): int
    {
        $eventId = 'EVT_'.Str::upper(Str::random(8));

        $eventType = $this->argument('eventType');
        $contactId = $this->argument('contactId');
        $workflowId = $this->option('workflowId');
        $workflowVersionId = $this->option('workflowVersionId');
        $enrollmentId = $this->option('enrollmentId');
        $companyId = $this->option('companyId');
        $source = $this->option('source');
        $category = $this->option('category');
        $correlationKey = $this->option('correlationKey');

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

        $this->line(str_repeat('-', 60));
        $this->comment('Next suggested step: process pending workflow events');
        $this->comment('Example: php artisan workflow:process');
        $this->line(str_repeat('-', 60));

        return self::SUCCESS;
    }
}
