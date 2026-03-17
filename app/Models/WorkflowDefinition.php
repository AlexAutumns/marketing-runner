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

    /**
     * WorkflowDefinition is the stable workflow master record.
     *
     * It stores the workflow identity and lightweight campaign context,
     * but it should not become a duplicate of the campaign-builder domain.
     *
     * Keep only the campaign fields that the workflow genuinely needs
     * for identification, routing, filtering, and explainability.
     */
    protected $fillable = [
        'WorkflowID',
        'WorkflowKey',
        'WorkflowName',
        'WorkflowCategoryCode',
        'WorkflowDescription',
        'WorkflowStatusCode',
        'OwnerModule',
        'MarketingCampaignID',
        'CampaignTemplateID',
        'ObjectiveCode',
        'PlatformCode',
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
