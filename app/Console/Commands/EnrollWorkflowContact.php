<?php

namespace App\Console\Commands;

use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStepLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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

        $enrollmentId = 'ENR_'.Str::upper(Str::random(8));

        WorkflowEnrollment::create([
            'EnrollmentID' => $enrollmentId,
            'WorkflowID' => $workflowId,
            'WorkflowVersionID' => $workflowVersionId,
            'ContactID' => $contactId,
            'CompanyID' => $companyId,
            'CurrentStepKey' => 'AWAIT_SIGNAL',
            'EnrollmentStatusCode' => 'ACTIVE',
            'StartedAtUTC' => now(),
        ]);

        WorkflowStepLog::create([
            'StepLogID' => 'STP_'.Str::upper(Str::random(8)),
            'EnrollmentID' => $enrollmentId,
            'WorkflowID' => $workflowId,
            'WorkflowVersionID' => $workflowVersionId,
            'StepKey' => 'ENROLLMENT_CREATED',
            'StepTypeCode' => 'SYSTEM',
            'StepStatusCode' => 'COMPLETED',
            'Message' => 'Workflow enrollment created.',
            'DetailsJson' => [
                'contact_id' => $contactId,
                'company_id' => $companyId,
            ],
            'OccurredAtUTC' => now(),
        ]);

        $this->info('Enrollment created successfully.');
        $this->line("EnrollmentID: {$enrollmentId}");
        $this->line("WorkflowID: {$workflowId}");
        $this->line("WorkflowVersionID: {$workflowVersionId}");
        $this->line("ContactID: {$contactId}");

        return self::SUCCESS;
    }
}
