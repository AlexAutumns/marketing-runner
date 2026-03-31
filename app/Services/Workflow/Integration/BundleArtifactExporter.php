<?php

namespace App\Services\Workflow\Integration;

use Illuminate\Support\Facades\File;

final class BundleArtifactExporter
{
    public function export(
        array $bundle,
        array $consumePath,
        array $serviceTargetPackets,
        string $enrollmentId,
        ?string $correlationKey = null
    ): array {
        $directory = storage_path('app/workflow_demo_artifacts');
        File::ensureDirectoryExists($directory);

        $safeCorrelationKey = $correlationKey ?: 'NO_CORRELATION';
        $baseName = $enrollmentId.'_'.$safeCorrelationKey;

        $bundlePath = $directory.'/'.$baseName.'_bundle.json';
        $consumePathPath = $directory.'/'.$baseName.'_consume_path.json';
        $servicePacketsPath = $directory.'/'.$baseName.'_service_packets.json';
        $manifestPath = $directory.'/'.$baseName.'_manifest.json';

        $this->writeJson($bundlePath, $bundle);
        $this->writeJson($consumePathPath, $consumePath);
        $this->writeJson($servicePacketsPath, $serviceTargetPackets);

        $manifest = [
            'export_version' => 1,
            'enrollment_id' => $enrollmentId,
            'correlation_key' => $correlationKey,
            'files' => [
                'bundle' => $bundlePath,
                'consume_path' => $consumePathPath,
                'service_packets' => $servicePacketsPath,
            ],
        ];

        $this->writeJson($manifestPath, $manifest);

        return [
            'bundle' => $bundlePath,
            'consume_path' => $consumePathPath,
            'service_packets' => $servicePacketsPath,
            'manifest' => $manifestPath,
        ];
    }

    protected function writeJson(string $path, array $payload): void
    {
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
