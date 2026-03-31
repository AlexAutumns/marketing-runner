<?php

use App\Services\Workflow\Integration\BundleConsumePathBuilder;
use App\Services\Workflow\Integration\IntegrationEventNormalizer;
use App\Services\Workflow\Integration\ServiceTargetPacketBuilder;
use App\Services\Workflow\Integration\WorkflowSignalMapper;

it('normalizes a clicked email tracking payload into the workflow-facing event shape', function () {
    $normalized = app(IntegrationEventNormalizer::class)->normalizeEmailTrackingEvent([
        'event_type' => 'clicked',
        'contact_id' => 'CNT_001',
        'workflow_id' => 'WFL_001',
        'workflow_version_id' => 'WFLV_002',
        'enrollment_id' => 'ENR_TEST_001',
        'correlation_key' => 'CORR_TEST_001',
        'message_id' => 'MSG_TEST_001',
        'link_url' => 'https://example.com/offer',
        'occurred_at_utc' => '2026-04-01T10:00:00Z',
    ]);

    expect($normalized['contract_version'])->toBe(1)
        ->and($normalized['event_type'])->toBe('EMAIL_LINK_CLICKED')
        ->and($normalized['event_category'])->toBe('ENGAGEMENT')
        ->and($normalized['source_system'])->toBe('EMAIL_TRACKING')
        ->and($normalized['subject_type'])->toBe('CONTACT')
        ->and($normalized['subject_id'])->toBe('CNT_001')
        ->and($normalized['workflow_context']['workflow_id'])->toBe('WFL_001')
        ->and($normalized['workflow_context']['workflow_version_id'])->toBe('WFLV_002')
        ->and($normalized['workflow_context']['enrollment_id'])->toBe('ENR_TEST_001')
        ->and($normalized['workflow_context']['correlation_key'])->toBe('CORR_TEST_001')
        ->and($normalized['attributes']['message_id'])->toBe('MSG_TEST_001')
        ->and($normalized['attributes']['link_url'])->toBe('https://example.com/offer')
        ->and($normalized['attributes']['raw_event_type'])->toBe('clicked');
});

it('maps an email click signal into score and summary workflow actions', function () {
    $mappedActions = app(WorkflowSignalMapper::class)->mapEmailSignalToActions([
        'event_type' => 'EMAIL_LINK_CLICKED',
        'subject_id' => 'CNT_001',
    ]);

    expect($mappedActions)->toHaveCount(2)
        ->and($mappedActions[0]['action_type'])->toBe('APPLY_LEAD_SCORE')
        ->and($mappedActions[0]['target_type'])->toBe('CONTACT')
        ->and($mappedActions[0]['target_id'])->toBe('CNT_001')
        ->and($mappedActions[0]['payload']['score_rule_code'])->toBe('EMAIL_CLICK')
        ->and($mappedActions[1]['action_type'])->toBe('UPDATE_LEAD_SUMMARY')
        ->and($mappedActions[1]['target_type'])->toBe('CONTACT')
        ->and($mappedActions[1]['target_id'])->toBe('CNT_001')
        ->and($mappedActions[1]['payload']['summary_code'])->toBe('EMAIL_CLICKED');
});

it('returns no mapped actions for an unknown workflow-facing event type', function () {
    $mappedActions = app(WorkflowSignalMapper::class)->mapEmailSignalToActions([
        'event_type' => 'UNKNOWN_EVENT',
        'subject_id' => 'CNT_001',
    ]);

    expect($mappedActions)->toBe([]);
});

it('builds the hybrid consume path from a workflow bundle and keeps both local and crm-mvp views', function () {
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
        ->and($consumePath['local_demo_application']['subject_type'])->toBe('CONTACT')
        ->and($consumePath['local_demo_application']['subject_id'])->toBe('CNT_001')
        ->and($consumePath['local_demo_application']['score_updates'])->toBe(['EMAIL_CLICK'])
        ->and($consumePath['local_demo_application']['summary_updates'])->toBe(['EMAIL_CLICKED'])
        ->and($consumePath['crm_mvp_handoff']['handoff_type'])->toBe('CRM_MVP_WORKFLOW_HANDOFF')
        ->and($consumePath['crm_mvp_handoff']['workflow_context']['correlation_key'])->toBe('CORR_TEST_001')
        ->and($consumePath['crm_mvp_handoff']['subject']['subject_id'])->toBe('CNT_001')
        ->and($consumePath['crm_mvp_handoff']['service_actions'])->toHaveCount(2)
        ->and($consumePath['crm_mvp_handoff']['service_actions'][0]['target_type_code'])->toBe('SCORING_SERVICE')
        ->and($consumePath['crm_mvp_handoff']['service_actions'][0]['target_id'])->toBe('TS_SCORING')
        ->and($consumePath['crm_mvp_handoff']['service_actions'][1]['target_type_code'])->toBe('CRM_SERVICE')
        ->and($consumePath['crm_mvp_handoff']['service_actions'][1]['target_id'])->toBe('CRM_CONTACT_SUMMARY');
});

it('groups crm-mvp handoff actions into separate service target packets with preserved workflow context and subject', function () {
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
        ->and($packets[0]['workflow_context']['correlation_key'])->toBe('CORR_TEST_001')
        ->and($packets[0]['subject']['subject_id'])->toBe('CNT_001')
        ->and($packets[0]['actions'])->toHaveCount(1)
        ->and($packets[0]['actions'][0]['payload']['score_rule_code'])->toBe('EMAIL_CLICK')
        ->and($packets[1]['target_type_code'])->toBe('CRM_SERVICE')
        ->and($packets[1]['target_id'])->toBe('CRM_CONTACT_SUMMARY')
        ->and($packets[1]['workflow_context']['correlation_key'])->toBe('CORR_TEST_001')
        ->and($packets[1]['subject']['subject_id'])->toBe('CNT_001')
        ->and($packets[1]['actions'])->toHaveCount(1)
        ->and($packets[1]['actions'][0]['payload']['summary_code'])->toBe('EMAIL_CLICKED');
});
