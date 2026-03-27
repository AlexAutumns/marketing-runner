<?php

namespace App\Console\Commands;

use App\Services\Workflow\WorkflowEventProcessor;
use Illuminate\Console\Command;

class ProcessWorkflowEvents extends Command
{
    protected $signature = 'workflow:process';

    protected $description = 'Process pending workflow events';

    public function handle(WorkflowEventProcessor $processor): int
    {
        $this->newLine();
        $this->info('WORKFLOW EVENT PROCESSOR (STEP-AWARE + ACTION QUEUE)');
        $this->line(str_repeat('-', 70));

        $result = $processor->processPendingEvents();

        $this->line('Pending workflow events : '.$result['total_pending']);
        $this->line(str_repeat('-', 70));

        if (! empty($result['results'])) {
            foreach ($result['results'] as $index => $detail) {
                $event = $detail['event'];
                $eventResult = $detail['result'];

                $this->info('Event '.($index + 1));
                $this->line('  EventID       : '.$event->EventID);
                $this->line('  EventType     : '.$event->EventTypeCode);
                $this->line('  ContactID     : '.($event->ContactID ?: '[none]'));
                $this->line('  EnrollmentID  : '.($event->WorkflowEnrollmentID ?: '[none]'));
                $this->line('  Result        : '.strtoupper($eventResult['status'] ?? 'unknown'));
                $this->line('  Message       : '.($eventResult['message'] ?? '[none]'));
                $this->line(str_repeat('-', 70));
            }
        } else {
            $this->comment('No pending events were available to process.');
            $this->line(str_repeat('-', 70));
        }

        $this->info('Workflow processing summary');
        $this->line('Processed : '.$result['processed']);
        $this->line('Ignored   : '.$result['ignored']);
        $this->line('Failed    : '.$result['failed']);
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
