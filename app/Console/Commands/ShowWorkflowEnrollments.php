<?php

namespace App\Console\Commands;

use App\Models\WorkflowEnrollment;
use Illuminate\Console\Command;

class ShowWorkflowEnrollments extends Command
{
    protected $signature = 'workflow:show-enrollments
                            {--contactId= : Filter by contact ID}
                            {--workflowId= : Filter by workflow ID}
                            {--status= : Filter by enrollment status}
                            {--limit=20 : Limit result count}
                            {--detail : Show detailed output}';

    protected $description = 'Inspect workflow enrollments with optional filters and detail output';

    public function handle(): int
    {
        $contactId = $this->option('contactId');
        $workflowId = $this->option('workflowId');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $detail = (bool) $this->option('detail');

        if ($limit <= 0) {
            $this->error('Validation failed: --limit must be greater than 0.');

            return self::FAILURE;
        }

        $query = WorkflowEnrollment::query()->orderByDesc('StartedAtUTC');

        if ($contactId) {
            $query->where('ContactID', $contactId);
        }

        if ($workflowId) {
            $query->where('WorkflowID', $workflowId);
        }

        if ($status) {
            $query->where('EnrollmentStatusCode', strtoupper($status));
        }

        $enrollments = $query->limit($limit)->get();

        $this->newLine();
        $this->info('WORKFLOW ENROLLMENT INSPECTION');
        $this->line(str_repeat('-', 70));
        $this->line('Filters');
        $this->line('  ContactID : '.($contactId ?: '[any]'));
        $this->line('  WorkflowID: '.($workflowId ?: '[any]'));
        $this->line('  Status    : '.($status ?: '[any]'));
        $this->line('  Limit     : '.$limit);
        $this->line('  Detail    : '.($detail ? 'yes' : 'no'));
        $this->line(str_repeat('-', 70));

        if ($enrollments->isEmpty()) {
            $this->warn('No workflow enrollments matched the current filters.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        foreach ($enrollments as $enrollment) {
            $this->line('EnrollmentID        : '.$enrollment->EnrollmentID);
            $this->line('  ContactID         : '.($enrollment->ContactID ?: '[none]'));
            $this->line('  WorkflowID        : '.($enrollment->WorkflowID ?: '[none]'));
            $this->line('  WorkflowVersionID : '.($enrollment->WorkflowVersionID ?: '[none]'));
            $this->line('  CurrentStepKey    : '.($enrollment->CurrentStepKey ?: '[none]'));
            $this->line('  Status            : '.($enrollment->EnrollmentStatusCode ?: '[none]'));
            $this->line('  StartedAtUTC      : '.($enrollment->StartedAtUTC?->toDateTimeString() ?: '[none]'));
            $this->line('  CompletedAtUTC    : '.($enrollment->CompletedAtUTC?->toDateTimeString() ?: '[none]'));

            if ($detail) {
                $this->line('  CompanyID         : '.($enrollment->CompanyID ?: '[none]'));
                $this->line('  WaitingUntilUTC   : '.($enrollment->WaitingUntilUTC?->toDateTimeString() ?: '[none]'));
                $this->line('  CompletedReason   : '.($enrollment->CompletedReasonCode ?: '[none]'));
                $this->line('  LastEventID       : '.($enrollment->LastEventID ?: '[none]'));
                $this->line('  LastActionAtUTC   : '.($enrollment->LastActionAtUTC?->toDateTimeString() ?: '[none]'));
            }

            $this->line(str_repeat('-', 70));
        }

        return self::SUCCESS;
    }
}
