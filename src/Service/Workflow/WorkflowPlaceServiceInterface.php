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

use Doctrine\DBAL\Exception as DBALException;
use Pimcore\Model\Element\ElementInterface;

/**
 * Current places of workflows using the state_table marking store, which keeps them in
 * element_workflow_state instead of an element field.
 *
 * @internal
 */
interface WorkflowPlaceServiceInterface
{
    /**
     * @return array<string, string[]> places per workflow name, without workflows the element has no place in
     *
     * @throws DBALException
     */
    public function getPlaces(ElementInterface $element): array;

    /**
     * Mapping of the workflowPlaces system field: one keyword sub field per state_table workflow.
     */
    public function getMapping(): array;
}
