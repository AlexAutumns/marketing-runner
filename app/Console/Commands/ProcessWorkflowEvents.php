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
        $this->info('WORKFLOW EVENT PROCESSOR (STEP-AWARE)');
        $this->line(str_repeat('-', 70));

        $result = $processor->processPendingEvents();

        $this->line('Pending events found : '.$result['total_pending']);
        $this->line(str_repeat('-', 70));

        if (! empty($result['details'])) {
            foreach ($result['details'] as $index => $detail) {
                $this->info('Event '.($index + 1));
                $this->line('  EventID       : '.$detail['event_id']);
                $this->line('  EventType     : '.$detail['event_type']);
                $this->line('  ContactID     : '.($detail['contact_id'] ?? '[none]'));
                $this->line('  EnrollmentID  : '.($detail['enrollment_id'] ?? '[none]'));
                $this->line('  Result        : '.strtoupper($detail['result']));
                $this->line('  Message       : '.$detail['message']);
                $this->line(str_repeat('-', 70));
            }
        } else {
            $this->comment('No pending events were available to process.');
            $this->line(str_repeat('-', 70));
        }

        $this->info('Processing summary');
        $this->line('Processed : '.$result['processed']);
        $this->line('Ignored   : '.$result['ignored']);
        $this->line('Failed    : '.$result['failed']);
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
