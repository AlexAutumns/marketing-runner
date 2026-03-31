<?php

namespace App\Services\Workflow\Integration;

final class ServiceTargetPacketBuilder
{
    public function build(array $crmMvpHandoff): array
    {
        $workflowContext = $crmMvpHandoff['workflow_context'] ?? [];
        $subject = $crmMvpHandoff['subject'] ?? [];
        $serviceActions = $crmMvpHandoff['service_actions'] ?? [];

        $grouped = [];

        foreach ($serviceActions as $serviceAction) {
            $targetTypeCode = $serviceAction['target_type_code'] ?? 'UNKNOWN_TARGET_TYPE';
            $targetId = $serviceAction['target_id'] ?? 'UNKNOWN_TARGET';
            $groupKey = $targetTypeCode.'::'.$targetId;

            if (! array_key_exists($groupKey, $grouped)) {
                $grouped[$groupKey] = [
                    'packet_version' => 1,
                    'packet_type' => 'SERVICE_TARGET_PACKET',
                    'target_type_code' => $targetTypeCode,
                    'target_id' => $targetId,
                    'workflow_context' => $workflowContext,
                    'subject' => $subject,
                    'actions' => [],
                ];
            }

            $grouped[$groupKey]['actions'][] = $serviceAction;
        }

        return array_values($grouped);
    }
}
