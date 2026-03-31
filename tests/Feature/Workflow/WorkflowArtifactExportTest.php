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

it('previews the bundle consume path for a real queued action bundle', function () {
    $context = prepareQueuedActionContext($this);

    $this->artisan('workflow:preview-bundle-consume-path', [
        'enrollmentId' => $context['enrollment_id'],
        '--correlationKey' => $context['correlation_key'],
    ])
        ->expectsOutputToContain('BUNDLE CONSUME PATH')
        ->expectsOutputToContain('local_demo_application')
        ->expectsOutputToContain('CRM_MVP_WORKFLOW_HANDOFF')
        ->assertExitCode(0);
});

it('exports bundle artifacts for a real queued action bundle', function () {
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

    $servicePackets = json_decode(File::get($servicePacketsPath), true);

    expect($servicePackets)->toBeArray()
        ->and($servicePackets)->toHaveCount(2)
        ->and($servicePackets[0]['packet_type'])->toBe('SERVICE_TARGET_PACKET')
        ->and($servicePackets[1]['packet_type'])->toBe('SERVICE_TARGET_PACKET');

    $targetIds = collect($servicePackets)->pluck('target_id')->sort()->values()->all();

    expect($targetIds)->toBe([
        'CRM_CONTACT_SUMMARY',
        'TS_SCORING',
    ]);
});

it('handles the no-actions-found export case safely', function () {
    $this->artisan('workflow:export-bundle-artifacts', [
        'enrollmentId' => 'ENR_DOES_NOT_EXIST',
        '--correlationKey' => 'CORR_NO_ACTIONS',
    ])
        ->expectsOutputToContain('No queued actions were found for the supplied filters.')
        ->assertExitCode(0);
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
