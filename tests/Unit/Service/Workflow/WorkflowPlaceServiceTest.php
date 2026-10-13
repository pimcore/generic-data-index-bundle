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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Workflow;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowPlaceService;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Workflow\Manager;
use Pimcore\Workflow\MarkingStore\StateTableMarkingStore;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @internal
 */
final class WorkflowPlaceServiceTest extends Unit
{
    public function testPlacesAreSplitPerWorkflow(): void
    {
        $connection = $this->makeEmpty(Connection::class, [
            'fetchAllKeyValue' => function (string $query, array $params) {
                $this->assertSame(
                    ['cid' => 12, 'ctype' => 'object', 'workflows' => ['product_workflow', 'review_workflow']],
                    $params
                );

                return ['product_workflow' => 'edit_text,edit_images', 'review_workflow' => 'open'];
            },
        ]);

        $service = new WorkflowPlaceService($this->createManager(), $connection);

        $this->assertSame(
            ['product_workflow' => ['edit_text', 'edit_images'], 'review_workflow' => ['open']],
            $service->getPlaces($this->makeEmpty(Concrete::class, ['getId' => 12]))
        );
    }

    public function testEmptyPlaceIsIgnored(): void
    {
        $connection = $this->makeEmpty(Connection::class, [
            'fetchAllKeyValue' => ['product_workflow' => ''],
        ]);

        $service = new WorkflowPlaceService($this->createManager(), $connection);

        $this->assertSame([], $service->getPlaces($this->makeEmpty(Concrete::class, ['getId' => 12])));
    }

    public function testNoQueryWithoutStateTableWorkflow(): void
    {
        $connection = $this->makeEmpty(Connection::class, [
            'fetchAllKeyValue' => Expected::never(),
        ]);

        $service = new WorkflowPlaceService($this->createManager(['method_workflow' => false]), $connection);

        $this->assertSame([], $service->getPlaces($this->makeEmpty(Concrete::class, ['getId' => 12])));
    }

    public function testMappingContainsStateTableWorkflowsOnly(): void
    {
        $service = new WorkflowPlaceService($this->createManager(), $this->makeEmpty(Connection::class));

        $this->assertSame(
            [
                'type' => 'object',
                'dynamic' => false,
                'properties' => [
                    'product_workflow' => ['type' => 'keyword'],
                    'review_workflow' => ['type' => 'keyword'],
                ],
            ],
            $service->getMapping()
        );
    }

    /**
     * @param array<string, bool> $workflows workflow name => uses the state_table marking store
     */
    private function createManager(
        array $workflows = ['product_workflow' => true, 'method_workflow' => false, 'review_workflow' => true]
    ): Manager {
        return $this->make(Manager::class, [
            'getAllWorkflows' => array_keys($workflows),
            'getWorkflowByName' => fn (string $name) => $this->makeEmpty(WorkflowInterface::class, [
                'getMarkingStore' => $workflows[$name]
                    ? new StateTableMarkingStore($name)
                    : new MethodMarkingStore(),
            ]),
        ]);
    }
}
