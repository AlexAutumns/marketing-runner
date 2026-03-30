<?php

namespace App\Console\Commands;

use App\Models\WorkflowVersion;
use Illuminate\Console\Command;

class WorkflowDraftAddConditionValue extends Command
{
    protected $signature = 'workflow:draft:add-condition-value
                            {workflowVersionId : Draft workflow version identifier}
                            {stepKey : Step key to update}
                            {conditionType : Condition type to update}
                            {value : Value to append into config.values}';

    protected $description = 'Append a value into an existing draft workflow condition list';

    public function handle(): int
    {
        $workflowVersionId = $this->argument('workflowVersionId');
        $stepKey = $this->argument('stepKey');
        $conditionType = $this->argument('conditionType');
        $value = $this->argument('value');

        $workflowVersion = WorkflowVersion::query()->find($workflowVersionId);

        if (! $workflowVersion) {
            $this->error("Workflow version [{$workflowVersionId}] was not found.");

            return self::FAILURE;
        }

        if ($workflowVersion->VersionStatusCode !== 'DRAFT') {
            $this->error("Workflow version [{$workflowVersionId}] is not in DRAFT status.");

            return self::FAILURE;
        }

        $stepGraph = $workflowVersion->StepGraphJson ?? [];
        $steps = $stepGraph['steps'] ?? [];

        $stepFound = false;
        $conditionFound = false;
        $valueAlreadyPresent = false;

        foreach ($steps as $stepIndex => $step) {
            if (($step['key'] ?? null) !== $stepKey) {
                continue;
            }

            $stepFound = true;
            $conditions = $step['conditions'] ?? [];

            foreach ($conditions as $conditionIndex => $condition) {
                if (($condition['type'] ?? null) !== $conditionType) {
                    continue;
                }

                $conditionFound = true;

                $values = $condition['config']['values'] ?? null;

                if (! is_array($values)) {
                    $this->error("Condition [{$conditionType}] on step [{$stepKey}] does not expose config.values as an array.");

                    return self::FAILURE;
                }

                if (in_array($value, $values, true)) {
                    $valueAlreadyPresent = true;
                    break 2;
                }

                $steps[$stepIndex]['conditions'][$conditionIndex]['config']['values'][] = $value;

                $stepGraph['steps'] = $steps;
                $workflowVersion->StepGraphJson = $stepGraph;

                // Clear any previously stored hash because the draft definition changed.
                $workflowVersion->GraphDefinitionHash = null;
                $workflowVersion->save();

                $this->newLine();
                $this->info('WORKFLOW DRAFT CONDITION VALUE ADDED');
                $this->line(str_repeat('-', 70));
                $this->line('WorkflowVersionID : '.$workflowVersion->WorkflowVersionID);
                $this->line('StepKey           : '.$stepKey);
                $this->line('ConditionType     : '.$conditionType);
                $this->line('Added Value       : '.$value);
                $this->line(str_repeat('-', 70));

                return self::SUCCESS;
            }

            break;
        }

        if (! $stepFound) {
            $this->error("Step [{$stepKey}] was not found in workflow version [{$workflowVersionId}].");

            return self::FAILURE;
        }

        if (! $conditionFound) {
            $this->error("Condition type [{$conditionType}] was not found on step [{$stepKey}].");

            return self::FAILURE;
        }

        if ($valueAlreadyPresent) {
            $this->warn("Value [{$value}] already exists in condition [{$conditionType}] on step [{$stepKey}].");

            return self::SUCCESS;
        }

        $this->error('No update was made.');

        return self::FAILURE;
    }
}
