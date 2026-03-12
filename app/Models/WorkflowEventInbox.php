<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowEventInbox extends Model
{
    protected $table = 'CrmDB_Workflow_Event_Inbox';

    protected $primaryKey = 'EventID';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'EventID',
        'EventTypeCode',
        'EventCategoryCode',
        'EventSourceCode',
        'ContactID',
        'CompanyID',
        'WorkflowID',
        'WorkflowVersionID',
        'WorkflowEnrollmentID',
        'CorrelationKey',
        'DedupeKey',
        'OccurredAtUTC',
        'PayloadJson',
        'ProcessingStatusCode',
        'ProcessedAtUTC',
        'ProcessingErrorMessage',
    ];

    protected $casts = [
        'PayloadJson' => 'array',
        'OccurredAtUTC' => 'datetime',
        'ProcessedAtUTC' => 'datetime',
    ];
}
