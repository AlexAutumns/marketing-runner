<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepLog extends Model
{
    protected $table = 'CrmDB_Workflow_Step_Log';

    protected $primaryKey = 'StepLogID';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'StepLogID',
        'EnrollmentID',
        'WorkflowID',
        'WorkflowVersionID',
        'StepKey',
        'StepTypeCode',
        'StepStatusCode',
        'BranchKey',
        'RelatedEventID',
        'RelatedActionQueueID',
        'Message',
        'DetailsJson',
        'OccurredAtUTC',
    ];

    protected $casts = [
        'DetailsJson' => 'array',
        'OccurredAtUTC' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(WorkflowEnrollment::class, 'EnrollmentID', 'EnrollmentID');
    }
}
