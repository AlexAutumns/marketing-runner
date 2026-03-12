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
        $result = $processor->processPendingEvents();

        $this->info('Workflow event processing complete.');
        $this->line('Processed: '.$result['processed']);
        $this->line('Ignored: '.$result['ignored']);
        $this->line('Failed: '.$result['failed']);

        return self::SUCCESS;
    }
}
