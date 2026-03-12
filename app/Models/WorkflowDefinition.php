<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    protected $table = 'CrmDB_Workflow_Definition';

    protected $primaryKey = 'WorkflowID';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'WorkflowID',
        'WorkflowKey',
        'WorkflowName',
        'WorkflowCategoryCode',
        'WorkflowDescription',
        'WorkflowStatusCode',
        'OwnerModule',
        'IsReusable',
        'IsSystem',
    ];

    protected $casts = [
        'IsReusable' => 'boolean',
        'IsSystem' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class, 'WorkflowID', 'WorkflowID');
    }
}
