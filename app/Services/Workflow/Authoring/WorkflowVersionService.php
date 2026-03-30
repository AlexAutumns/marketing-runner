<?php

namespace App\Services\Workflow\Authoring;

use App\Models\WorkflowVersion;
use DomainException;
use Illuminate\Support\Str;

class WorkflowVersionService
{
    public function __construct(
        protected WorkflowGraphValidator $graphValidator
    ) {}

    /**
     * Clone an existing workflow version into a new draft version.
     *
     * This is the safe correction path:
     * - keep the original version intact
     * - create a new editable draft
     * - let validation/publish happen separately
     */
    public function cloneToDraft(
        WorkflowVersion $sourceVersion,
        ?string $changeSummary = null
    ): WorkflowVersion {
        $nextVersionNo = (int) WorkflowVersion::query()
            ->where('WorkflowID', $sourceVersion->WorkflowID)
            ->max('VersionNo') + 1;

        return WorkflowVersion::create([
            'WorkflowVersionID' => 'WFLV_'.Str::upper(Str::random(8)),
            'WorkflowID' => $sourceVersion->WorkflowID,
            'VersionNo' => $nextVersionNo,
            'VersionStatusCode' => 'DRAFT',
            'WorkflowProfileCode' => $sourceVersion->WorkflowProfileCode,
            'TriggerConfigJson' => $sourceVersion->TriggerConfigJson,
            'ConditionConfigJson' => $sourceVersion->ConditionConfigJson,
            'ActionConfigJson' => $sourceVersion->ActionConfigJson,
            'StepGraphJson' => $sourceVersion->StepGraphJson,
            'GraphDefinitionHash' => null,
            'SupersedesWorkflowVersionID' => $sourceVersion->WorkflowVersionID,
            'ChangeSummary' => $changeSummary ?: 'Draft cloned from '.$sourceVersion->WorkflowVersionID,
            'PublishedAtUTC' => null,
        ]);
    }

    /**
     * Publish a draft version after validation and duplicate-definition checks.
     *
     * We allow duplicate checks against ACTIVE and PUBLISHED because the current
     * branch still contains seeded ACTIVE reference versions that function as
     * publish-like reference baselines.
     */
    public function publishDraft(
        WorkflowVersion $workflowVersion,
        ?string $changeSummary = null
    ): WorkflowVersion {
        if ($workflowVersion->VersionStatusCode !== 'DRAFT') {
            throw new DomainException('Only DRAFT workflow versions can be published.');
        }

        $validation = $this->graphValidator->validateWorkflowVersion($workflowVersion);

        if (! $validation['ok']) {
            throw new DomainException(
                'Workflow draft failed validation: '.implode(' | ', $validation['errors'])
            );
        }

        $definitionHash = $this->calculateDefinitionHash($workflowVersion);

        $duplicateExists = WorkflowVersion::query()
            ->where('WorkflowID', $workflowVersion->WorkflowID)
            ->where('WorkflowVersionID', '!=', $workflowVersion->WorkflowVersionID)
            ->where('GraphDefinitionHash', $definitionHash)
            ->whereIn('VersionStatusCode', ['ACTIVE', 'PUBLISHED'])
            ->exists();

        if ($duplicateExists) {
            throw new DomainException('An identical active or published workflow version already exists.');
        }

        $workflowVersion->fill([
            'GraphDefinitionHash' => $definitionHash,
            'VersionStatusCode' => 'PUBLISHED',
            'ChangeSummary' => $changeSummary ?: $workflowVersion->ChangeSummary,
            'PublishedAtUTC' => now(),
        ]);

        $workflowVersion->save();

        return $workflowVersion->refresh();
    }

    /**
     * Build a stable definition hash from the parts that define behavior.
     *
     * We sort associative arrays recursively so harmless key order differences
     * do not create false “new versions”.
     */
    public function calculateDefinitionHash(WorkflowVersion $workflowVersion): string
    {
        $payload = [
            'WorkflowProfileCode' => $workflowVersion->WorkflowProfileCode,
            'TriggerConfigJson' => $this->sortRecursively($workflowVersion->TriggerConfigJson ?? []),
            'ConditionConfigJson' => $this->sortRecursively($workflowVersion->ConditionConfigJson ?? []),
            'ActionConfigJson' => $this->sortRecursively($workflowVersion->ActionConfigJson ?? []),
            'StepGraphJson' => $this->sortRecursively($workflowVersion->StepGraphJson ?? []),
        ];

        return hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function sortRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                if ($this->isAssociative($item)) {
                    $value[$key] = $this->sortRecursively($item);
                } else {
                    $value[$key] = array_map(
                        fn ($child) => is_array($child) && $this->isAssociative($child)
                            ? $this->sortRecursively($child)
                            : $child,
                        $item
                    );
                }
            }
        }

        if ($this->isAssociative($value)) {
            ksort($value);
        }

        return $value;
    }

    protected function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
