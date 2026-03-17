<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;

class WorkflowFoundationSeeder extends Seeder
{
    public function run(): void
    {
        WorkflowDefinition::updateOrCreate(
            ['WorkflowID' => 'WFL_001'],
            [
                'WorkflowKey' => 'MKT_WELCOME_FLOW',
                'WorkflowName' => 'Marketing Welcome Flow',
                'WorkflowCategoryCode' => 'MARKETING',
                'WorkflowDescription' => 'Foundation workflow used to prove the workflow kernel in a marketing-campaign context.',
                'WorkflowStatusCode' => 'ACTIVE',
                'OwnerModule' => 'Marketing Automation',
                'MarketingCampaignID' => 'MC_001',
                'CampaignTemplateID' => 'CT_001',
                'ObjectiveCode' => 'LEAD_GENERATION',
                'PlatformCode' => 'LINKEDIN',
                'IsReusable' => true,
                'IsSystem' => false,
            ]
        );

        // The sample workflow version deliberately uses broad, stable event categories
        // and clearer engagement-oriented event type names so the workflow kernel stays
        // realistic enough for future integration, while still remaining flexible during development.
        WorkflowVersion::updateOrCreate(
            ['WorkflowVersionID' => 'WFLV_001'],
            [
                'WorkflowID' => 'WFL_001',
                'VersionNo' => 1,
                'VersionStatusCode' => 'ACTIVE',
                'TriggerConfigJson' => [
                    'start_mode' => 'manual_enrollment',
                ],
                'ConditionConfigJson' => [
                    'notes' => 'Step-aware processing is driven primarily from StepGraphJson in the workflow-kernel foundation.',
                    'supported_event_categories' => [
                        'ENGAGEMENT',
                        'CAMPAIGN_CONTEXT',
                        'WORKFLOW_CONTROL',
                    ],
                ],
                'ActionConfigJson' => [
                    'on_step_completion' => [
                        'AWAIT_SIGNAL' => [
                            [
                                'action_type' => 'SEND_FOLLOW_UP_EMAIL',
                                'target_type' => 'CONTACT',
                                'payload' => [
                                    'template_key' => 'WELCOME_TEMPLATE',
                                    'notes' => 'Placeholder queued action for workflow kernel demo',
                                ],
                            ],
                        ],
                    ],
                ],
                'StepGraphJson' => [
                    'initial_step' => 'AWAIT_SIGNAL',
                    'steps' => [
                        [
                            'key' => 'AWAIT_SIGNAL',
                            'type' => 'WAIT_FOR_EVENT',
                            'accepted_categories' => [
                                'ENGAGEMENT',
                                'WORKFLOW_CONTROL',
                            ],
                            'accepted_events' => [
                                'MANUAL_TEST_EVENT',
                                'EMAIL_LINK_CLICKED',
                                'BROCHURE_LINK_CLICKED',
                                'FORM_SUBMITTED',
                            ],
                            'next' => 'COMPLETE',
                            'terminal_on_match' => false,
                        ],
                        [
                            'key' => 'COMPLETE',
                            'type' => 'END',
                            'terminal' => true,
                        ],
                    ],
                ],
            ]
        );
    }
}
