<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds the workflow-kernel reference workflow used in local development,
 * demos, and architecture validation.
 *
 * This seeder is intentionally more than random sample data:
 * - it represents the current workflow-kernel contract
 * - it reflects campaign-aware workflow definition design
 * - it reflects the step-aware event model
 * - it reflects placeholder action-queue behavior
 *
 * Keep this file aligned with the workflow-kernel design direction.
 * Do not turn it into a dump of unrelated test cases.
 */
class WorkflowFoundationSeeder extends Seeder
{
    public function run(): void
    {
        // The workflow definition stores the stable workflow identity plus lightweight
        // campaign context. It should stay campaign-aware, but it should not become
        // a duplicate of the campaign-builder domain.
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
        // The workflow version stores the rule/config shape that the processor reads.
        // The workflow identity and the workflow behavior stay separate on purpose so
        // later rule changes do not overwrite the meaning of older workflow runs.
        WorkflowVersion::updateOrCreate(
            ['WorkflowVersionID' => 'WFLV_001'],
            [
                'WorkflowID' => 'WFL_001',
                'VersionNo' => 1,
                'VersionStatusCode' => 'ACTIVE',
                'TriggerConfigJson' => [
                    'start_mode' => 'manual_enrollment',
                ],

                // ConditionConfigJson currently stores broad event-model support metadata.
                // Categories are intentionally stable and broad, while step-level matching
                // stays event-type specific inside the step graph.
                'ConditionConfigJson' => [
                    'notes' => 'The reference workflow version now proves multi-step event progression plus timed waiting and resume behavior as the v3 phase 2 baseline.',
                    'supported_event_categories' => [
                        'ENGAGEMENT',
                        'CAMPAIGN_CONTEXT',
                        'WORKFLOW_CONTROL',
                    ],
                ],

                // ActionConfigJson stores workflow-decided action intent at configuration level.
                // The processor reads this config and writes action queue rows, but it does not
                // execute external side effects directly.
                'ActionConfigJson' => [
                    'on_step_completion' => [
                        'AWAIT_INITIAL_ENGAGEMENT' => [
                            [
                                'action_type' => 'UPDATE_WORKFLOW_PROPERTY',
                                'target_type' => 'CONTACT',
                                'payload' => [
                                    'property_key' => 'last_engagement_stage',
                                    'property_value' => 'INITIAL_ENGAGEMENT_CONFIRMED',
                                    'notes' => 'Set after the first engagement signal is accepted.',
                                ],
                            ],
                        ],
                        'AWAIT_STRONGER_SIGNAL' => [
                            [
                                'action_type' => 'MARK_FOR_SCORING_HANDOFF',
                                'target_type' => 'CONTACT',
                                'payload' => [
                                    'handoff_reason' => 'FORM_SUBMISSION_CONFIRMED',
                                    'notes' => 'Queue downstream scoring handoff after stronger conversion intent is confirmed.',
                                ],
                            ],
                        ],
                    ],
                ],

                // StepGraphJson is the stored workflow path used by the processor.
                // The current sample keeps this small on purpose so the branch proves
                // step-aware workflow behavior without overcommitting to a final builder format.
                'StepGraphJson' => [
                    'initial_step' => 'AWAIT_INITIAL_ENGAGEMENT',
                    'steps' => [
                        [
                            'key' => 'AWAIT_INITIAL_ENGAGEMENT',
                            'type' => 'WAIT_FOR_EVENT',
                            'accepted_categories' => [
                                'ENGAGEMENT',
                                'WORKFLOW_CONTROL',
                            ],
                            'accepted_events' => [
                                'MANUAL_TEST_EVENT',
                                'EMAIL_LINK_CLICKED',
                                'BROCHURE_LINK_CLICKED',
                            ],
                            'next' => 'WAIT_BEFORE_STRONGER_SIGNAL',
                            'terminal_on_match' => false,
                        ],
                        [
                            'key' => 'WAIT_BEFORE_STRONGER_SIGNAL',
                            'type' => 'WAIT_FOR_TIME',
                            'accepted_categories' => [
                                'WORKFLOW_CONTROL',
                            ],
                            'accepted_events' => [
                                'WAIT_TIMER_REACHED',
                            ],
                            'wait_config' => [
                                'mode' => 'DELAY_MINUTES',
                                'value' => 20,
                            ],
                            'next' => 'AWAIT_STRONGER_SIGNAL',
                            'terminal_on_match' => false,
                        ],
                        [
                            'key' => 'AWAIT_STRONGER_SIGNAL',
                            'type' => 'WAIT_FOR_EVENT',
                            'accepted_categories' => [
                                'ENGAGEMENT',
                                'WORKFLOW_CONTROL',
                            ],
                            'accepted_events' => [
                                'FORM_SUBMITTED',
                            ],
                            'next' => 'COMPLETE',
                            'terminal_on_match' => false,
                        ],
                        [
                            'key' => 'COMPLETE',
                            'type' => 'TERMINAL',
                            'terminal' => true,
                        ],
                    ],
                ],
            ]
        );
    }
}
