<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\Workflow\Manager;
use Pimcore\Workflow\MarkingStore\StateTableMarkingStore;

/**
 * @internal
 */
final class WorkflowPlaceService implements WorkflowPlaceServiceInterface
{
    /**
     * @var string[]|null
     */
    private ?array $stateTableWorkflowNames = null;

    public function __construct(
        private readonly Manager $workflowManager,
        private readonly Connection $connection,
    ) {
    }

    public function getPlaces(ElementInterface $element): array
    {
        $workflowNames = $this->getStateTableWorkflowNames();
        if ($workflowNames === []) {
            return [];
        }

        // Rows of workflows that are not configured (anymore) are ignored, so the index matches the mapping.
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT workflow, place FROM element_workflow_state
                WHERE cid = :cid AND ctype = :ctype AND workflow IN (:workflows)
                ORDER BY workflow',
            [
                'cid' => $element->getId(),
                'ctype' => Service::getElementType($element),
                'workflows' => $workflowNames,
            ],
            ['workflows' => ArrayParameterType::STRING]
        );

        $places = [];
        foreach ($rows as $workflowName => $place) {
            // The marking store keeps all places of a "workflow" type workflow comma-separated in one row.
            $workflowPlaces = array_values(array_filter(
                explode(',', (string) $place),
                static fn (string $value) => $value !== ''
            ));
            if ($workflowPlaces !== []) {
                $places[(string) $workflowName] = $workflowPlaces;
            }
        }

        return $places;
    }

    public function getMapping(): array
    {
        $properties = [];
        foreach ($this->getStateTableWorkflowNames() as $workflowName) {
            $properties[$workflowName] = ['type' => 'keyword'];
        }

        return [
            'type' => 'object',
            // Rows of a workflow added after the last mapping update must not create dynamic text fields.
            'dynamic' => false,
            'properties' => $properties,
        ];
    }

    /**
     * @return string[]
     */
    private function getStateTableWorkflowNames(): array
    {
        if ($this->stateTableWorkflowNames !== null) {
            return $this->stateTableWorkflowNames;
        }

        $workflowNames = [];
        foreach ($this->workflowManager->getAllWorkflows() as $workflowName) {
            $markingStore = $this->workflowManager->getWorkflowByName($workflowName)?->getMarkingStore();
            if ($markingStore instanceof StateTableMarkingStore) {
                $workflowNames[] = $workflowName;
            }
        }

        $this->stateTableWorkflowNames = $workflowNames;

        return $workflowNames;
    }
}
