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

        WorkflowEventInbox::create([
            'EventID' => $eventId,
            'EventTypeCode' => $this->argument('eventType'),
            'EventCategoryCode' => $this->option('category'),
            'EventSourceCode' => $this->option('source'),
            'ContactID' => $this->argument('contactId'),
            'CompanyID' => $this->option('companyId'),
            'WorkflowID' => $this->option('workflowId'),
            'WorkflowVersionID' => $this->option('workflowVersionId'),
            'WorkflowEnrollmentID' => $this->option('enrollmentId'),
            'CorrelationKey' => $this->option('correlationKey'),
            'OccurredAtUTC' => now(),
            'PayloadJson' => [
                'injected_by' => 'workflow:event command',
            ],
            'ProcessingStatusCode' => 'PENDING',
        ]);

        $this->info('Workflow event injected successfully.');
        $this->line("EventID: {$eventId}");
        $this->line('EventTypeCode: '.$this->argument('eventType'));
        $this->line('ContactID: '.$this->argument('contactId'));

        return self::SUCCESS;
    }
}
