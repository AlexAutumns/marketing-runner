<?php

namespace App\Console\Commands;

use App\Models\WorkflowActionQueue;
use App\Services\Workflow\Integration\QueuedActionBundleProjector;
use Illuminate\Console\Command;

class PreviewQueuedActionBundle extends Command
{
    protected $signature = 'workflow:preview-action-bundle
                            {enrollmentId : Workflow enrollment identifier}
                            {--correlationKey= : Optional correlation key filter}';

    protected $description = 'Preview a grouped bundle of queued workflow actions for one enrollment';

    public function handle(QueuedActionBundleProjector $bundleProjector): int
    {
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

        $this->newLine();
        $this->info('QUEUED ACTION BUNDLE');
        $this->line(str_repeat('-', 70));
        $this->line('EnrollmentID    : '.$enrollmentId);
        $this->line('CorrelationKey  : '.($correlationKey ?: '[any]'));
        $this->line('Action Count    : '.count($actions));
        $this->line(str_repeat('-', 70));
        $this->line(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line(str_repeat('-', 70));

        return self::SUCCESS;
    }
}
