<?php

namespace App\Console\Commands;

use App\Models\WorkflowActionQueue;
use App\Services\Workflow\Integration\BundleArtifactExporter;
use App\Services\Workflow\Integration\BundleConsumePathBuilder;
use App\Services\Workflow\Integration\QueuedActionBundleProjector;
use App\Services\Workflow\Integration\ServiceTargetPacketBuilder;
use Illuminate\Console\Command;

class ExportBundleArtifacts extends Command
{
    protected $signature = 'workflow:export-bundle-artifacts
                            {enrollmentId : Workflow enrollment identifier}
                            {--correlationKey= : Optional correlation key filter}';

    protected $description = 'Export bundle artifacts for local demo proof and CRM MVP handoff discussion';

    public function handle(
        QueuedActionBundleProjector $bundleProjector,
        BundleConsumePathBuilder $consumePathBuilder,
        ServiceTargetPacketBuilder $packetBuilder,
        BundleArtifactExporter $artifactExporter
    ): int {
        $enrollmentId = $this->argument('enrollmentId');
        $correlationKey = $this->option('correlationKey');

        $query = WorkflowActionQueue::query()
            ->where('EnrollmentID', $enrollmentId)
            ->orderBy('created_at');

        if ($correlationKey) {
            $query->where('CorrelationKey', $correlationKey);
        }

        $actions = $query->get()->all();

        if ($actions === []) {
            $this->warn('No queued actions were found for the supplied filters.');

            return self::SUCCESS;
        }

        $bundle = $bundleProjector->project($actions);
        $consumePath = $consumePathBuilder->build($bundle);
        $crmMvpHandoff = $consumePath['crm_mvp_handoff'] ?? [];
        $serviceTargetPackets = $packetBuilder->build($crmMvpHandoff);

        $paths = $artifactExporter->export(
            bundle: $bundle,
            consumePath: $consumePath,
            serviceTargetPackets: $serviceTargetPackets,
            enrollmentId: $enrollmentId,
            correlationKey: $correlationKey
        );

        $this->newLine();
        $this->info('WORKFLOW BUNDLE ARTIFACTS EXPORTED');
        $this->line(str_repeat('-', 70));
        $this->line('EnrollmentID    : '.$enrollmentId);
        $this->line('CorrelationKey  : '.($correlationKey ?: '[any]'));
        $this->line(str_repeat('-', 70));
        $this->line('Bundle          : '.$paths['bundle']);
        $this->line('Consume Path    : '.$paths['consume_path']);
        $this->line('Service Packets : '.$paths['service_packets']);
        $this->line('Manifest        : '.$paths['manifest']);
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
