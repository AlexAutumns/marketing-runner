<?php

use App\Models\WorkflowEnrollment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/workflow_demo_artifacts'));
});

it('previews the bundle consume path for a real queued action bundle and shows both local and crm handoff sections', function () {
    $context = prepareQueuedActionContext($this);

    $this->artisan('workflow:preview-bundle-consume-path', [
        'enrollmentId' => $context['enrollment_id'],
        '--correlationKey' => $context['correlation_key'],
    ])
        ->expectsOutputToContain('BUNDLE CONSUME PATH')
        ->expectsOutputToContain('local_demo_application')
        ->expectsOutputToContain('crm_mvp_handoff')
        ->expectsOutputToContain('SCORING_SERVICE')
        ->expectsOutputToContain('CRM_CONTACT_SUMMARY')
        ->assertExitCode(0);
});

it('exports bundle artifacts with correct content in bundle, consume path, service packet, and manifest files', function () {
    $context = prepareQueuedActionContext($this);

    $this->artisan('workflow:export-bundle-artifacts', [
        'enrollmentId' => $context['enrollment_id'],
        '--correlationKey' => $context['correlation_key'],
    ])
        ->expectsOutputToContain('WORKFLOW BUNDLE ARTIFACTS EXPORTED')
        ->assertExitCode(0);

    $baseName = $context['enrollment_id'].'_'.$context['correlation_key'];
    $directory = storage_path('app/workflow_demo_artifacts');

    $bundlePath = $directory.'/'.$baseName.'_bundle.json';
    $consumePathPath = $directory.'/'.$baseName.'_consume_path.json';
    $servicePacketsPath = $directory.'/'.$baseName.'_service_packets.json';
    $manifestPath = $directory.'/'.$baseName.'_manifest.json';

    expect(File::exists($bundlePath))->toBeTrue()
        ->and(File::exists($consumePathPath))->toBeTrue()
        ->and(File::exists($servicePacketsPath))->toBeTrue()
        ->and(File::exists($manifestPath))->toBeTrue();

    $bundle = json_decode(File::get($bundlePath), true);
    $consumePath = json_decode(File::get($consumePathPath), true);
    $servicePackets = json_decode(File::get($servicePacketsPath), true);
    $manifest = json_decode(File::get($manifestPath), true);

    expect($bundle['bundle_type'])->toBe('CONTACT_UPDATE_BUNDLE')
        ->and($bundle['workflow_context']['workflow_id'])->toBe('WFL_001')
        ->and($bundle['workflow_context']['workflow_version_id'])->toBe('WFLV_002')
        ->and($bundle['workflow_context']['enrollment_id'])->toBe($context['enrollment_id'])
        ->and($bundle['workflow_context']['correlation_key'])->toBe($context['correlation_key'])
        ->and($bundle['subject']['subject_id'])->toBe($context['contact_id'])
        ->and($bundle['instructions'])->toHaveCount(2);

    expect($consumePath['local_demo_application']['score_updates'])->toBe(['EMAIL_CLICK'])
        ->and($consumePath['local_demo_application']['summary_updates'])->toBe(['EMAIL_CLICKED'])
        ->and($consumePath['crm_mvp_handoff']['handoff_type'])->toBe('CRM_MVP_WORKFLOW_HANDOFF')
        ->and($consumePath['crm_mvp_handoff']['service_actions'])->toHaveCount(2);

    expect($servicePackets)->toBeArray()
        ->and($servicePackets)->toHaveCount(2)
        ->and($servicePackets[0]['packet_type'])->toBe('SERVICE_TARGET_PACKET')
        ->and($servicePackets[1]['packet_type'])->toBe('SERVICE_TARGET_PACKET');

    $targetIds = collect($servicePackets)->pluck('target_id')->sort()->values()->all();

    expect($targetIds)->toBe([
        'CRM_CONTACT_SUMMARY',
        'TS_SCORING',
    ]);

    $scoringPacket = collect($servicePackets)->firstWhere('target_id', 'TS_SCORING');
    $crmPacket = collect($servicePackets)->firstWhere('target_id', 'CRM_CONTACT_SUMMARY');

    expect($scoringPacket['actions'][0]['instruction_type'])->toBe('UPDATE_CONTACT_LEAD_SCORE')
        ->and($scoringPacket['actions'][0]['payload']['score_rule_code'])->toBe('EMAIL_CLICK')
        ->and($crmPacket['actions'][0]['instruction_type'])->toBe('UPDATE_CONTACT_LEAD_SUMMARY')
        ->and($crmPacket['actions'][0]['payload']['summary_code'])->toBe('EMAIL_CLICKED');

    expect($manifest['export_version'])->toBe(1)
        ->and($manifest['enrollment_id'])->toBe($context['enrollment_id'])
        ->and($manifest['correlation_key'])->toBe($context['correlation_key'])
        ->and($manifest['files']['bundle'])->toBe($bundlePath)
        ->and($manifest['files']['consume_path'])->toBe($consumePathPath)
        ->and($manifest['files']['service_packets'])->toBe($servicePacketsPath);
});

it('handles the no-actions-found export case safely without creating the artifact directory', function () {
    $directory = storage_path('app/workflow_demo_artifacts');

    $this->artisan('workflow:export-bundle-artifacts', [
        'enrollmentId' => 'ENR_DOES_NOT_EXIST',
        '--correlationKey' => 'CORR_NO_ACTIONS',
    ])
        ->expectsOutputToContain('No queued actions were found for the supplied filters.')
        ->assertExitCode(0);

    expect(File::exists($directory))->toBeFalse();
});

function prepareQueuedActionContext($testCase): array
{
    $contactId = DB::table('contacts')->value('contact_id');

    $testCase->artisan('workflow:enroll', [
        'contactId' => $contactId,
        'workflowId' => 'WFL_001',
        'workflowVersionId' => 'WFLV_002',
    ])->assertExitCode(0);

    $enrollment = WorkflowEnrollment::query()->firstOrFail();
    $correlationKey = 'CORR_TEST_EXPORT_001';

    $testCase->artisan('workflow:event', [
        'eventType' => 'EMAIL_LINK_CLICKED',
        'contactId' => $contactId,
        '--workflowId' => 'WFL_001',
        '--workflowVersionId' => 'WFLV_002',
        '--enrollmentId' => $enrollment->EnrollmentID,
        '--source' => 'EMAIL_TRACKING',
        '--correlationKey' => $correlationKey,
    ])->assertExitCode(0);

    $testCase->artisan('workflow:process')->assertExitCode(0);

    return [
        'contact_id' => $contactId,
        'enrollment_id' => $enrollment->EnrollmentID,
        'correlation_key' => $correlationKey,
    ];
}
