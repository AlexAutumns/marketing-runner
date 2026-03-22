<?php

namespace App\Console\Commands;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStepLog;
use App\Models\WorkflowVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Enroll a contact into a workflow version by creating the workflow runtime
 * state record for that contact.
 *
 * This command is the bridge between:
 * - stored workflow definition/version data
 * and
 * - a live workflow run for one contact
 *
 * It does not execute workflow processing itself.
 * It creates the initial runtime entry point that later events can act on.
 */
class EnrollWorkflowContact extends Command
{
    protected $signature = 'workflow:enroll
                            {contactId : Contact identifier}
                            {workflowId : Workflow identifier}
                            {workflowVersionId : Workflow version identifier}
                            {--companyId= : Optional company identifier}';

    protected $description = 'Enroll a contact into a workflow version';

    public function handle(): int
    {
        $contactId = $this->argument('contactId');
        $workflowId = $this->argument('workflowId');
        $workflowVersionId = $this->argument('workflowVersionId');
        $companyId = $this->option('companyId');

        $this->newLine();
        $this->info('WORKFLOW ENROLLMENT COMMAND');
        $this->line(str_repeat('-', 70));
        $this->line("Input ContactID        : {$contactId}");
        $this->line("Input WorkflowID       : {$workflowId}");
        $this->line("Input WorkflowVersionID: {$workflowVersionId}");
        $this->line('Input CompanyID        : '.($companyId ?: '[none]'));
        $this->line(str_repeat('-', 70));

        // Missing workflow structure is a hard failure because enrollment should never
        // create runtime state against unresolved workflow identity or version context.

        // 1. Validate workflow definition
        $workflow = WorkflowDefinition::find($workflowId);

        if (! $workflow) {
            $this->error("Validation failed: Workflow definition [{$workflowId}] was not found.");

            return self::FAILURE;
        }

        $this->info("Validated workflow definition: {$workflow->WorkflowName} ({$workflow->WorkflowKey})");
        $this->line('Workflow MarketingCampaignID : '.($workflow->MarketingCampaignID ?: '[none]'));
        $this->line('Workflow CampaignTemplateID  : '.($workflow->CampaignTemplateID ?: '[none]'));
        $this->line('Workflow ObjectiveCode       : '.($workflow->ObjectiveCode ?: '[none]'));
        $this->line('Workflow PlatformCode        : '.($workflow->PlatformCode ?: '[none]'));

        // 2. Validate workflow version
        $version = WorkflowVersion::find($workflowVersionId);

        if (! $version) {
            $this->error("Validation failed: Workflow version [{$workflowVersionId}] was not found.");

            return self::FAILURE;
        }

        $this->info("Validated workflow version: Version {$version->VersionNo} [{$version->VersionStatusCode}]");

        // 3. Validate version belongs to workflow
        if ($version->WorkflowID !== $workflow->WorkflowID) {
            $this->error("Validation failed: Workflow version [{$workflowVersionId}] does not belong to workflow [{$workflowId}].");

            return self::FAILURE;
        }

        $this->info('Validated workflow-version relationship.');

        // 4. Check if the contact already has an active enrollment in this workflow version
        // A contact should not silently receive duplicate active-like runs for the
        // same workflow version unless that behavior is intentionally designed later.
        $existingEnrollment = WorkflowEnrollment::where('ContactID', $contactId)
            ->where('WorkflowID', $workflowId)
            ->where('WorkflowVersionID', $workflowVersionId)
            ->whereIn('EnrollmentStatusCode', ['ACTIVE', 'WAITING', 'PAUSED'])
            ->first();

        if ($existingEnrollment) {
            $this->warn('Enrollment skipped: Contact already has an active-like enrollment for this workflow version.');
            $this->line("Existing EnrollmentID  : {$existingEnrollment->EnrollmentID}");
            $this->line("Existing Status        : {$existingEnrollment->EnrollmentStatusCode}");
            $this->line('Existing Current Step  : '.($existingEnrollment->CurrentStepKey ?: '[none]'));

            return self::SUCCESS;
        }

        // 5. Resolve the initial workflow step from the versioned step graph
        $stepGraph = $version->StepGraphJson ?? [];
        $initialStepKey = $this->getInitialStepKey($stepGraph);
        $initialStep = $this->getStepDefinition($stepGraph, $initialStepKey);

        if (! $initialStepKey || ! $initialStep) {
            $this->error('Enrollment failed: Workflow version does not expose a valid initial step in StepGraphJson.');

            return self::FAILURE;
        }

        // 6. Create enrollment
        $enrollmentId = 'ENR_'.Str::upper(Str::random(8));

        $enrollment = WorkflowEnrollment::create([
            'EnrollmentID' => $enrollmentId,
            'WorkflowID' => $workflowId,
            'WorkflowVersionID' => $workflowVersionId,
            'ContactID' => $contactId,
            'CompanyID' => $companyId,
            'CurrentStepKey' => $initialStepKey,
            'EnrollmentStatusCode' => 'ACTIVE',
            'StartedAtUTC' => now(),
        ]);

        $this->info('Enrollment created successfully.');
        $this->line("EnrollmentID           : {$enrollment->EnrollmentID}");
        $this->line("CurrentStepKey         : {$enrollment->CurrentStepKey}");
        $this->line("EnrollmentStatusCode   : {$enrollment->EnrollmentStatusCode}");

        // 7. Create initial step log
        $stepLogId = 'STP_'.Str::upper(Str::random(8));

        WorkflowStepLog::create([
            'StepLogID' => $stepLogId,
            'EnrollmentID' => $enrollmentId,
            'WorkflowID' => $workflowId,
            'WorkflowVersionID' => $workflowVersionId,
            'StepKey' => 'ENROLLMENT_CREATED',
            'StepTypeCode' => $initialStep['type'] ?? 'UNKNOWN',
            'StepStatusCode' => 'COMPLETED',
            'Message' => 'Workflow enrollment created.',
            'DetailsJson' => [
                'contact_id' => $contactId,
                'company_id' => $companyId,
                'workflow_key' => $workflow->WorkflowKey,
                'workflow_version_no' => $version->VersionNo,
            ],
            'OccurredAtUTC' => now(),
        ]);

        $this->info('Initial step log written.');
        $this->line("StepLogID              : {$stepLogId}");

        $this->line(str_repeat('-', 70));
        $this->comment('Next recommended command: workflow:event');
        $this->comment("Example: php artisan workflow:event MANUAL_TEST_EVENT {$contactId} --workflowId={$workflowId} --workflowVersionId={$workflowVersionId} --enrollmentId={$enrollmentId}");
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }

    protected function getInitialStepKey(array $stepGraph): ?string
    {
        return $stepGraph['initial_step'] ?? null;
    }

    protected function getStepDefinition(array $stepGraph, ?string $stepKey): ?array
    {
        if (! $stepKey) {
            return null;
        }

        $steps = $stepGraph['steps'] ?? [];

        foreach ($steps as $step) {
            if (($step['key'] ?? null) === $stepKey) {
                return $step;
            }
        }

        return null;
    }
}
