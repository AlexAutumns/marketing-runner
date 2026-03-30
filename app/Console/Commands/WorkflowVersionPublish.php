<?php

namespace App\Console\Commands;

use App\Models\WorkflowVersion;
use App\Services\Workflow\Authoring\WorkflowVersionService;
use DomainException;
use Illuminate\Console\Command;

class WorkflowVersionPublish extends Command
{
    protected $signature = 'workflow:version:publish
                            {workflowVersionId : Draft workflow version to publish}
                            {--changeSummary= : Optional publish-time change summary override}';

    protected $description = 'Publish a validated draft workflow version';

    public function handle(WorkflowVersionService $versionService): int
    {
        $workflowVersionId = $this->argument('workflowVersionId');
        $changeSummary = $this->option('changeSummary');

        $workflowVersion = WorkflowVersion::query()->find($workflowVersionId);

        if (! $workflowVersion) {
            $this->error("Workflow version [{$workflowVersionId}] was not found.");

            return self::FAILURE;
        }

        try {
            $publishedVersion = $versionService->publishDraft($workflowVersion, $changeSummary);
        } catch (DomainException $exception) {
            $this->newLine();
            $this->error('WORKFLOW VERSION PUBLISH FAILED');
            $this->line(str_repeat('-', 70));
            $this->line($exception->getMessage());
            $this->line(str_repeat('-', 70));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('WORKFLOW VERSION PUBLISHED');
        $this->line(str_repeat('-', 70));
        $this->line('WorkflowVersionID : '.$publishedVersion->WorkflowVersionID);
        $this->line('WorkflowID : '.$publishedVersion->WorkflowID);
        $this->line('VersionNo : '.$publishedVersion->VersionNo);
        $this->line('Status : '.$publishedVersion->VersionStatusCode);
        $this->line('Profile : '.($publishedVersion->WorkflowProfileCode ?: '[none]'));
        $this->line('DefinitionHash : '.($publishedVersion->GraphDefinitionHash ?: '[none]'));
        $this->line('ChangeSummary : '.($publishedVersion->ChangeSummary ?: '[none]'));
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
