<?php

namespace App\Console\Commands;

use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStepLog;
use Illuminate\Console\Command;

class ShowWorkflowStepLogs extends Command
{
    protected $signature = 'workflow:show-step-logs
                            {--enrollmentId= : Filter by enrollment ID}
                            {--contactId= : Filter by contact ID through workflow enrollment lookup}
                            {--workflowId= : Filter by workflow ID}
                            {--status= : Filter by step status}
                            {--limit=20 : Limit result count}
                            {--detail : Show detailed output}';

    protected $description = 'Inspect workflow step log rows with optional filters and detail output';

    public function handle(): int
    {
        $enrollmentId = $this->option('enrollmentId');
        $contactId = $this->option('contactId');
        $workflowId = $this->option('workflowId');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $detail = (bool) $this->option('detail');

        if ($limit <= 0) {
            $this->error('Validation failed: --limit must be greater than 0.');

            return self::FAILURE;
        }

        $query = WorkflowStepLog::query()->orderByDesc('OccurredAtUTC');

        if ($enrollmentId) {
            $query->where('EnrollmentID', $enrollmentId);
        }

        if ($workflowId) {
            $query->where('WorkflowID', $workflowId);
        }

        if ($status) {
            $query->where('StepStatusCode', strtoupper($status));
        }

        if ($contactId) {
            $matchingEnrollmentIds = WorkflowEnrollment::query()
                ->where('ContactID', $contactId)
                ->pluck('EnrollmentID');

            $query->whereIn('EnrollmentID', $matchingEnrollmentIds);
        }

        $logs = $query->limit($limit)->get();

        $this->newLine();
        $this->info('WORKFLOW STEP LOG INSPECTION');
        $this->line(str_repeat('-', 70));
        $this->line('Filters');
        $this->line('  EnrollmentID: '.($enrollmentId ?: '[any]'));
        $this->line('  ContactID   : '.($contactId ?: '[any]'));
        $this->line('  WorkflowID  : '.($workflowId ?: '[any]'));
        $this->line('  Status      : '.($status ?: '[any]'));
        $this->line('  Limit       : '.$limit);
        $this->line('  Detail      : '.($detail ? 'yes' : 'no'));
        $this->line(str_repeat('-', 70));

        if ($logs->isEmpty()) {
            $this->warn('No workflow step logs matched the current filters.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        foreach ($logs as $log) {
            $this->line('StepLogID          : '.$log->StepLogID);
            $this->line('  EnrollmentID     : '.($log->EnrollmentID ?: '[none]'));
            $this->line('  WorkflowID       : '.($log->WorkflowID ?: '[none]'));
            $this->line('  WorkflowVersionID: '.($log->WorkflowVersionID ?: '[none]'));
            $this->line('  StepKey          : '.($log->StepKey ?: '[none]'));
            $this->line('  StepType         : '.($log->StepTypeCode ?: '[none]'));
            $this->line('  StepStatus       : '.($log->StepStatusCode ?: '[none]'));
            $this->line('  RelatedEventID   : '.($log->RelatedEventID ?: '[none]'));
            $this->line('  OccurredAtUTC    : '.($log->OccurredAtUTC?->toDateTimeString() ?: '[none]'));

            if ($detail) {
                $this->line('  BranchKey        : '.($log->BranchKey ?: '[none]'));
                $this->line('  RelatedActionID  : '.($log->RelatedActionQueueID ?: '[none]'));
                $this->line('  Message          : '.($log->Message ?: '[none]'));
                $this->line('  DetailsJson      : '.json_encode($log->DetailsJson));
            }

            $this->line(str_repeat('-', 70));
        }

        return self::SUCCESS;
    }
}
