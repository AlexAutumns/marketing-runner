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
                'WorkflowDescription' => 'Foundation workflow used to prove the workflow kernel.',
                'WorkflowStatusCode' => 'ACTIVE',
                'OwnerModule' => 'Marketing Automation',
                'IsReusable' => true,
                'IsSystem' => false,
            ]
        );

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
                    'notes' => 'Step-aware processing is driven primarily from StepGraphJson in v2 foundation slice.',
                ],
                'ActionConfigJson' => [
                    'on_step_completion' => [
                        'AWAIT_SIGNAL' => [
                            [
                                'action_type' => 'SEND_EMAIL',
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
                            'accepted_events' => ['MANUAL_TEST_EVENT', 'EMAIL_CLICK'],
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
