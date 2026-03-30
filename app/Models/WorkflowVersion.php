<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{
    protected $table = 'CrmDB_Workflow_Version';

    protected $primaryKey = 'WorkflowVersionID';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'WorkflowVersionID',
        'WorkflowID',
        'VersionNo',
        'VersionStatusCode',
        'WorkflowProfileCode',
        'TriggerConfigJson',
        'ConditionConfigJson',
        'ActionConfigJson',
        'StepGraphJson',
        'GraphDefinitionHash',
        'SupersedesWorkflowVersionID',
        'ChangeSummary',
        'PublishedAtUTC',
    ];

    protected $casts = [
        'TriggerConfigJson' => 'array',
        'ConditionConfigJson' => 'array',
        'ActionConfigJson' => 'array',
        'StepGraphJson' => 'array',
        'PublishedAtUTC' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'WorkflowID', 'WorkflowID');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(WorkflowEnrollment::class, 'WorkflowVersionID', 'WorkflowVersionID');
    }
}
