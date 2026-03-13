<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowActionQueue extends Model
{
    protected $table = 'CrmDB_Workflow_Action_Queue';

    protected $primaryKey = 'ActionQueueID';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ActionQueueID',
        'EnrollmentID',
        'WorkflowID',
        'WorkflowVersionID',
        'ActionTypeCode',
        'ActionStatusCode',
        'TargetTypeCode',
        'TargetID',
        'RelatedEventID',
        'CorrelationKey',
        'PayloadJson',
        'ScheduledForUTC',
        'AttemptCount',
        'LastAttemptAtUTC',
    ];

    protected $casts = [
        'PayloadJson' => 'array',
        'ScheduledForUTC' => 'datetime',
        'LastAttemptAtUTC' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(WorkflowEnrollment::class, 'EnrollmentID', 'EnrollmentID');
    }
}
