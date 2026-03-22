<?php

namespace App\Console\Commands;

use App\Models\WorkflowEventInbox;
use Illuminate\Console\Command;

class ShowWorkflowEvents extends Command
{
    protected $signature = 'workflow:show-events
                            {--contactId= : Filter by contact ID}
                            {--workflowId= : Filter by workflow ID}
                            {--eventType= : Filter by event type}
                            {--category= : Filter by event category}
                            {--status= : Filter by processing status}
                            {--limit=20 : Limit result count}
                            {--detail : Show detailed output}';

    protected $description = 'Inspect workflow event inbox rows with optional filters and detail output';

    public function handle(): int
    {
        $contactId = $this->option('contactId');
        $workflowId = $this->option('workflowId');
        $eventType = $this->option('eventType');
        $category = $this->option('category');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $detail = (bool) $this->option('detail');

        if ($limit <= 0) {
            $this->error('Validation failed: --limit must be greater than 0.');

            return self::FAILURE;
        }

        $query = WorkflowEventInbox::query()->orderByDesc('OccurredAtUTC');

        if ($contactId) {
            $query->where('ContactID', $contactId);
        }

        if ($workflowId) {
            $query->where('WorkflowID', $workflowId);
        }

        if ($eventType) {
            $query->where('EventTypeCode', strtoupper($eventType));
        }

        if ($category) {
            $query->where('EventCategoryCode', strtoupper($category));
        }

        if ($status) {
            $query->where('ProcessingStatusCode', strtoupper($status));
        }

        $events = $query->limit($limit)->get();

        $this->newLine();
        $this->info('WORKFLOW EVENT INSPECTION');
        $this->line(str_repeat('-', 70));
        $this->line('Filters');
        $this->line('  ContactID : '.($contactId ?: '[any]'));
        $this->line('  WorkflowID: '.($workflowId ?: '[any]'));
        $this->line('  EventType : '.($eventType ?: '[any]'));
        $this->line('  Category  : '.($category ?: '[any]'));
        $this->line('  Status    : '.($status ?: '[any]'));
        $this->line('  Limit     : '.$limit);
        $this->line('  Detail    : '.($detail ? 'yes' : 'no'));
        $this->line(str_repeat('-', 70));

        if ($events->isEmpty()) {
            $this->warn('No workflow events matched the current filters.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            $this->line('EventID             : '.$event->EventID);
            $this->line('  EventType         : '.($event->EventTypeCode ?: '[none]'));
            $this->line('  EventCategory     : '.($event->EventCategoryCode ?: '[none]'));
            $this->line('  EventSource       : '.($event->EventSourceCode ?: '[none]'));
            $this->line('  ContactID         : '.($event->ContactID ?: '[none]'));
            $this->line('  WorkflowID        : '.($event->WorkflowID ?: '[none]'));
            $this->line('  EnrollmentID      : '.($event->WorkflowEnrollmentID ?: '[none]'));
            $this->line('  ProcessingStatus  : '.($event->ProcessingStatusCode ?: '[none]'));
            $this->line('  OccurredAtUTC     : '.($event->OccurredAtUTC?->toDateTimeString() ?: '[none]'));
            $this->line('  ProcessedAtUTC    : '.($event->ProcessedAtUTC?->toDateTimeString() ?: '[none]'));

            if ($detail) {
                $this->line('  WorkflowVersionID : '.($event->WorkflowVersionID ?: '[none]'));
                $this->line('  CompanyID         : '.($event->CompanyID ?: '[none]'));
                $this->line('  CorrelationKey    : '.($event->CorrelationKey ?: '[none]'));
                $this->line('  DedupeKey         : '.($event->DedupeKey ?: '[none]'));
                $this->line('  ErrorMessage      : '.($event->ProcessingErrorMessage ?: '[none]'));
                $this->line('  PayloadJson       : '.json_encode($event->PayloadJson));
            }

            $this->line(str_repeat('-', 70));
        }

        return self::SUCCESS;
    }
}
