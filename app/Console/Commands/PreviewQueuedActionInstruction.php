<?php

namespace App\Console\Commands;

use App\Models\WorkflowActionQueue;
use App\Services\Workflow\Integration\QueuedActionInstructionProjector;
use Illuminate\Console\Command;

class PreviewQueuedActionInstruction extends Command
{
    protected $signature = 'workflow:preview-action-instruction
                            {actionQueueId : Workflow action queue identifier}';

    protected $description = 'Preview how a real queued workflow action projects into a domain update instruction';

    public function handle(QueuedActionInstructionProjector $projector): int
    {
        $actionQueueId = $this->argument('actionQueueId');

        $actionQueue = WorkflowActionQueue::query()->find($actionQueueId);

        if (! $actionQueue) {
            $this->error("Workflow action queue row [{$actionQueueId}] was not found.");

            return self::FAILURE;
        }

        $instruction = $projector->project($actionQueue);

        $this->newLine();
        $this->info('QUEUED WORKFLOW ACTION');
        $this->line(str_repeat('-', 70));
        $this->line('ActionQueueID      : '.$actionQueue->ActionQueueID);
        $this->line('EnrollmentID       : '.($actionQueue->EnrollmentID ?: '[none]'));
        $this->line('WorkflowID         : '.($actionQueue->WorkflowID ?: '[none]'));
        $this->line('WorkflowVersionID  : '.($actionQueue->WorkflowVersionID ?: '[none]'));
        $this->line('ActionTypeCode     : '.($actionQueue->ActionTypeCode ?: '[none]'));
        $this->line('ActionStatusCode   : '.($actionQueue->ActionStatusCode ?: '[none]'));
        $this->line('TargetTypeCode     : '.($actionQueue->TargetTypeCode ?: '[none]'));
        $this->line('TargetID           : '.($actionQueue->TargetID ?: '[none]'));
        $this->line('CorrelationKey     : '.($actionQueue->CorrelationKey ?: '[none]'));
        $this->line('RelatedEventID     : '.($actionQueue->RelatedEventID ?: '[none]'));
        $this->line('PayloadJson        : '.json_encode($actionQueue->PayloadJson, JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        if ($instruction === null) {
            $this->warn('No projector rule exists yet for this ActionTypeCode.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        $this->info('PROJECTED DOMAIN INSTRUCTION');
        $this->line(str_repeat('-', 70));
        $this->line(json_encode($instruction, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
