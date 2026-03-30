<?php

namespace App\Console\Commands;

use App\Models\WorkflowVersion;
use App\Services\Workflow\Authoring\WorkflowGraphValidator;
use Illuminate\Console\Command;

class ValidateWorkflowVersion extends Command
{
    protected $signature = 'workflow:version:validate {workflowVersionId}';

    protected $description = 'Validate a stored workflow version definition';

    public function handle(WorkflowGraphValidator $validator): int
    {
        $workflowVersionId = $this->argument('workflowVersionId');

        $workflowVersion = WorkflowVersion::query()->find($workflowVersionId);

        if (! $workflowVersion) {
            $this->error("Workflow version [{$workflowVersionId}] was not found.");

            return self::FAILURE;
        }

        $result = $validator->validateWorkflowVersion($workflowVersion);

        $this->newLine();
        $this->info('WORKFLOW VERSION VALIDATION');
        $this->line(str_repeat('-', 70));
        $this->line('WorkflowVersionID : '.$workflowVersion->WorkflowVersionID);
        $this->line('WorkflowID        : '.$workflowVersion->WorkflowID);
        $this->line('VersionNo         : '.$workflowVersion->VersionNo);
        $this->line('Status            : '.$workflowVersion->VersionStatusCode);
        $this->line(str_repeat('-', 70));

        if ($result['ok']) {
            $this->info('Validation passed. No errors found.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        $this->error('Validation failed.');
        $this->line(str_repeat('-', 70));

        foreach ($result['errors'] as $index => $error) {
            $this->line(($index + 1).'. '.$error);
        }

        $this->line(str_repeat('-', 70));

        return self::FAILURE;
    }
}
