<?php

namespace App\Console\Commands;

use App\Models\WorkflowActionQueue;
use App\Services\Workflow\Integration\BundleConsumePathBuilder;
use App\Services\Workflow\Integration\QueuedActionBundleProjector;
use Illuminate\Console\Command;

class PreviewBundleConsumePath extends Command
{
    protected $signature = 'workflow:preview-bundle-consume-path
                            {enrollmentId : Workflow enrollment identifier}
                            {--correlationKey= : Optional correlation key filter}';

    protected $description = 'Preview the hybrid local + CRM MVP consume path for a queued action bundle';

    public function handle(
        QueuedActionBundleProjector $bundleProjector,
        BundleConsumePathBuilder $consumePathBuilder
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

        $this->newLine();
        $this->info('BUNDLE CONSUME PATH');
        $this->line(str_repeat('-', 70));
        $this->line('EnrollmentID   : '.$enrollmentId);
        $this->line('CorrelationKey : '.($correlationKey ?: '[any]'));
        $this->line(str_repeat('-', 70));
        $this->line(json_encode($consumePath, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
