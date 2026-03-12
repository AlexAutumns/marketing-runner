<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowEnrollment extends Model
{
    protected $table = 'CrmDB_Workflow_Enrollment';

    protected $primaryKey = 'EnrollmentID';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'EnrollmentID',
        'WorkflowID',
        'WorkflowVersionID',
        'ContactID',
        'CompanyID',
        'CurrentStepKey',
        'EnrollmentStatusCode',
        'WaitingUntilUTC',
        'CompletedReasonCode',
        'LastEventID',
        'LastActionAtUTC',
        'StartedAtUTC',
        'CompletedAtUTC',
    ];

    protected $casts = [
        'WaitingUntilUTC' => 'datetime',
        'LastActionAtUTC' => 'datetime',
        'StartedAtUTC' => 'datetime',
        'CompletedAtUTC' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'WorkflowID', 'WorkflowID');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'WorkflowVersionID', 'WorkflowVersionID');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class, 'EnrollmentID', 'EnrollmentID');
    }
}
