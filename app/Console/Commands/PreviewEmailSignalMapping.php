<?php

namespace App\Console\Commands;

use App\Services\Workflow\Integration\DomainUpdateInstructionBuilder;
use App\Services\Workflow\Integration\IntegrationEventNormalizer;
use App\Services\Workflow\Integration\WorkflowSignalMapper;
use Illuminate\Console\Command;

class PreviewEmailSignalMapping extends Command
{
    protected $signature = 'workflow:preview-email-signal
                            {eventType : clicked|opened|delivered|bounced}
                            {contactId : Contact identifier}
                            {--workflowId=WFL_001 : Workflow identifier}
                            {--workflowVersionId=WFLV_002 : Workflow version identifier}
                            {--enrollmentId= : Optional enrollment identifier}
                            {--correlationKey= : Optional correlation key}
                            {--messageId= : Optional message identifier}
                            {--linkUrl= : Optional tracked link URL}';

    protected $description = 'Preview normalized email signal shape and mapped workflow action output';

    public function handle(

        IntegrationEventNormalizer $normalizer,
        WorkflowSignalMapper $mapper,
        DomainUpdateInstructionBuilder $instructionBuilder

    ): int {
        $payload = [
            'event_type' => $this->argument('eventType'),
            'contact_id' => $this->argument('contactId'),
            'workflow_id' => $this->option('workflowId'),
            'workflow_version_id' => $this->option('workflowVersionId'),
            'enrollment_id' => $this->option('enrollmentId'),
            'correlation_key' => $this->option('correlationKey'),
            'message_id' => $this->option('messageId'),
            'link_url' => $this->option('linkUrl'),
            'occurred_at_utc' => now()->toISOString(),
        ];

        $normalizedEvent = $normalizer->normalizeEmailTrackingEvent($payload);
        $mappedActions = $mapper->mapEmailSignalToActions($normalizedEvent);
        $domainUpdateInstructions = $instructionBuilder->buildFromMappedActions(
            $mappedActions,
            $normalizedEvent
        );

        $this->newLine();
        $this->info('NORMALIZED EMAIL SIGNAL');
        $this->line(str_repeat('-', 70));
        $this->line(json_encode($normalizedEvent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        $this->info('MAPPED WORKFLOW ACTIONS');
        $this->line(str_repeat('-', 70));
        $this->line(json_encode($mappedActions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        $this->info('DOMAIN UPDATE INSTRUCTIONS');
        $this->line(str_repeat('-', 70));
        $this->line(json_encode($domainUpdateInstructions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
