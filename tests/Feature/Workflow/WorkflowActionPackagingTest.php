<?php

use App\Services\Workflow\Integration\BundleConsumePathBuilder;
use App\Services\Workflow\Integration\ServiceTargetPacketBuilder;

it('builds the hybrid consume path from a workflow bundle', function () {
    $bundle = [
        'bundle_version' => 1,
        'bundle_type' => 'CONTACT_UPDATE_BUNDLE',
        'workflow_context' => [
            'workflow_id' => 'WFL_001',
            'workflow_version_id' => 'WFLV_002',
            'enrollment_id' => 'ENR_TEST_001',
            'correlation_key' => 'CORR_TEST_001',
        ],
        'subject' => [
            'subject_type' => 'CONTACT',
            'subject_id' => 'CNT_001',
        ],
        'source_action_ids' => [
            'ACTQ_TEST_001',
            'ACTQ_TEST_002',
        ],
        'instructions' => [
            [
                'instruction_version' => 1,
                'instruction_type' => 'UPDATE_CONTACT_LEAD_SCORE',
                'subject_type' => 'CONTACT',
                'subject_id' => 'CNT_001',
                'workflow_context' => [
                    'workflow_id' => 'WFL_001',
                    'workflow_version_id' => 'WFLV_002',
                    'enrollment_id' => 'ENR_TEST_001',
                    'correlation_key' => 'CORR_TEST_001',
                    'related_event_id' => 'EVT_TEST_001',
                ],
                'changes' => [
                    'score_rule_code' => 'EMAIL_CLICK',
                ],
            ],
            [
                'instruction_version' => 1,
                'instruction_type' => 'UPDATE_CONTACT_LEAD_SUMMARY',
                'subject_type' => 'CONTACT',
                'subject_id' => 'CNT_001',
                'workflow_context' => [
                    'workflow_id' => 'WFL_001',
                    'workflow_version_id' => 'WFLV_002',
                    'enrollment_id' => 'ENR_TEST_001',
                    'correlation_key' => 'CORR_TEST_001',
                    'related_event_id' => 'EVT_TEST_001',
                ],
                'changes' => [
                    'summary_code' => 'EMAIL_CLICKED',
                ],
            ],
        ],
    ];

    $consumePath = app(BundleConsumePathBuilder::class)->build($bundle);

    expect($consumePath['consume_path_version'])->toBe(1)
        ->and($consumePath['bundle_type'])->toBe('CONTACT_UPDATE_BUNDLE')
        ->and($consumePath['local_demo_application']['subject_id'])->toBe('CNT_001')
        ->and($consumePath['local_demo_application']['score_updates'])->toBe(['EMAIL_CLICK'])
        ->and($consumePath['local_demo_application']['summary_updates'])->toBe(['EMAIL_CLICKED'])
        ->and($consumePath['crm_mvp_handoff']['handoff_type'])->toBe('CRM_MVP_WORKFLOW_HANDOFF')
        ->and($consumePath['crm_mvp_handoff']['service_actions'])->toHaveCount(2)
        ->and($consumePath['crm_mvp_handoff']['service_actions'][0]['target_type_code'])->toBe('SCORING_SERVICE')
        ->and($consumePath['crm_mvp_handoff']['service_actions'][1]['target_type_code'])->toBe('CRM_SERVICE');
});

it('groups crm mvp handoff actions into service target packets', function () {
    $crmMvpHandoff = [
        'handoff_version' => 1,
        'handoff_type' => 'CRM_MVP_WORKFLOW_HANDOFF',
        'workflow_context' => [
            'workflow_id' => 'WFL_001',
            'workflow_version_id' => 'WFLV_002',
            'enrollment_id' => 'ENR_TEST_001',
            'correlation_key' => 'CORR_TEST_001',
        ],
        'subject' => [
            'subject_type' => 'CONTACT',
            'subject_id' => 'CNT_001',
        ],
        'source_action_ids' => [
            'ACTQ_TEST_001',
            'ACTQ_TEST_002',
        ],
        'service_actions' => [
            [
                'target_type_code' => 'SCORING_SERVICE',
                'target_id' => 'TS_SCORING',
                'instruction_type' => 'UPDATE_CONTACT_LEAD_SCORE',
                'payload' => [
                    'score_rule_code' => 'EMAIL_CLICK',
                ],
                'references' => [
                    'lead_scoring_rule_code' => 'EMAIL_CLICK',
                ],
            ],
            [
                'target_type_code' => 'CRM_SERVICE',
                'target_id' => 'CRM_CONTACT_SUMMARY',
                'instruction_type' => 'UPDATE_CONTACT_LEAD_SUMMARY',
                'payload' => [
                    'summary_code' => 'EMAIL_CLICKED',
                ],
                'references' => [
                    'summary_code' => 'EMAIL_CLICKED',
                ],
            ],
        ],
    ];

    $packets = app(ServiceTargetPacketBuilder::class)->build($crmMvpHandoff);

    expect($packets)->toHaveCount(2)
        ->and($packets[0]['packet_type'])->toBe('SERVICE_TARGET_PACKET')
        ->and($packets[0]['target_type_code'])->toBe('SCORING_SERVICE')
        ->and($packets[0]['target_id'])->toBe('TS_SCORING')
        ->and($packets[0]['actions'])->toHaveCount(1)
        ->and($packets[1]['target_type_code'])->toBe('CRM_SERVICE')
        ->and($packets[1]['target_id'])->toBe('CRM_CONTACT_SUMMARY')
        ->and($packets[1]['actions'])->toHaveCount(1);
});
