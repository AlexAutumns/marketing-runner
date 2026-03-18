<?php

namespace App\Console\Commands;

use App\Models\WorkflowActionQueue;
use App\Models\WorkflowEnrollment;
use Illuminate\Console\Command;

class ShowWorkflowActions extends Command
{
    protected $signature = 'workflow:show-actions
                            {--enrollmentId= : Filter by enrollment ID}
                            {--contactId= : Filter by contact ID through workflow enrollment lookup}
                            {--workflowId= : Filter by workflow ID}
                            {--actionType= : Filter by action type}
                            {--status= : Filter by action status}
                            {--limit=20 : Limit result count}
                            {--detail : Show detailed output}';

    protected $description = 'Inspect workflow action queue rows with optional filters and detail output';

    public function handle(): int
    {
        $enrollmentId = $this->option('enrollmentId');
        $contactId = $this->option('contactId');
        $workflowId = $this->option('workflowId');
        $actionType = $this->option('actionType');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $detail = (bool) $this->option('detail');

        if ($limit <= 0) {
            $this->error('Validation failed: --limit must be greater than 0.');

            return self::FAILURE;
        }

        $query = WorkflowActionQueue::query()->orderByDesc('ScheduledForUTC');

        if ($enrollmentId) {
            $query->where('EnrollmentID', $enrollmentId);
        }

        if ($workflowId) {
            $query->where('WorkflowID', $workflowId);
        }

        if ($actionType) {
            $query->where('ActionTypeCode', strtoupper($actionType));
        }

        if ($status) {
            $query->where('ActionStatusCode', strtoupper($status));
        }

        if ($contactId) {
            $matchingEnrollmentIds = WorkflowEnrollment::query()
                ->where('ContactID', $contactId)
                ->pluck('EnrollmentID');

            $query->whereIn('EnrollmentID', $matchingEnrollmentIds);
        }

        $actions = $query->limit($limit)->get();

        $this->newLine();
        $this->info('WORKFLOW ACTION QUEUE INSPECTION');
        $this->line(str_repeat('-', 70));
        $this->line('Filters');
        $this->line('  EnrollmentID: '.($enrollmentId ?: '[any]'));
        $this->line('  ContactID   : '.($contactId ?: '[any]'));
        $this->line('  WorkflowID  : '.($workflowId ?: '[any]'));
        $this->line('  ActionType  : '.($actionType ?: '[any]'));
        $this->line('  Status      : '.($status ?: '[any]'));
        $this->line('  Limit       : '.$limit);
        $this->line('  Detail      : '.($detail ? 'yes' : 'no'));
        $this->line(str_repeat('-', 70));

        if ($actions->isEmpty()) {
            $this->warn('No workflow actions matched the current filters.');
            $this->line(str_repeat('-', 70));

            return self::SUCCESS;
        }

        foreach ($actions as $action) {
            $this->line('ActionQueueID      : '.$action->ActionQueueID);
            $this->line('  EnrollmentID     : '.($action->EnrollmentID ?: '[none]'));
            $this->line('  WorkflowID       : '.($action->WorkflowID ?: '[none]'));
            $this->line('  WorkflowVersionID: '.($action->WorkflowVersionID ?: '[none]'));
            $this->line('  ActionType       : '.($action->ActionTypeCode ?: '[none]'));
            $this->line('  ActionStatus     : '.($action->ActionStatusCode ?: '[none]'));
            $this->line('  TargetType       : '.($action->TargetTypeCode ?: '[none]'));
            $this->line('  TargetID         : '.($action->TargetID ?: '[none]'));
            $this->line('  ScheduledForUTC  : '.($action->ScheduledForUTC?->toDateTimeString() ?: '[none]'));

            if ($detail) {
                $this->line('  RelatedEventID   : '.($action->RelatedEventID ?: '[none]'));
                $this->line('  CorrelationKey   : '.($action->CorrelationKey ?: '[none]'));
                $this->line('  AttemptCount     : '.$action->AttemptCount);
                $this->line('  LastAttemptAtUTC : '.($action->LastAttemptAtUTC?->toDateTimeString() ?: '[none]'));
                $this->line('  PayloadJson      : '.json_encode($action->PayloadJson));
            }

            $this->line(str_repeat('-', 70));
        }

        return self::SUCCESS;
    }
}
