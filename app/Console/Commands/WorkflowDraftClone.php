<?php

namespace App\Console\Commands;

use App\Models\WorkflowVersion;
use App\Services\Workflow\Authoring\WorkflowVersionService;
use Illuminate\Console\Command;

class WorkflowDraftClone extends Command
{
    protected $signature = 'workflow:draft:clone
                            {sourceWorkflowVersionId : Existing workflow version to clone}
                            {--changeSummary= : Optional change summary for the new draft}';

    protected $description = 'Clone an existing workflow version into a new draft';

    public function handle(WorkflowVersionService $versionService): int
    {
        $sourceWorkflowVersionId = $this->argument('sourceWorkflowVersionId');
        $changeSummary = $this->option('changeSummary');

        $sourceVersion = WorkflowVersion::query()->find($sourceWorkflowVersionId);

        if (! $sourceVersion) {
            $this->error("Workflow version [{$sourceWorkflowVersionId}] was not found.");

            return self::FAILURE;
        }

        $draftVersion = $versionService->cloneToDraft($sourceVersion, $changeSummary);

        $this->newLine();
        $this->info('WORKFLOW DRAFT CLONE CREATED');
        $this->line(str_repeat('-', 70));
        $this->line('Source WorkflowVersionID : '.$sourceVersion->WorkflowVersionID);
        $this->line('New Draft WorkflowVersionID : '.$draftVersion->WorkflowVersionID);
        $this->line('WorkflowID : '.$draftVersion->WorkflowID);
        $this->line('VersionNo : '.$draftVersion->VersionNo);
        $this->line('Status : '.$draftVersion->VersionStatusCode);
        $this->line('Supersedes : '.($draftVersion->SupersedesWorkflowVersionID ?: '[none]'));
        $this->line('ChangeSummary : '.($draftVersion->ChangeSummary ?: '[none]'));
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
