<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow;

use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface WorkflowServiceInterface
{
    public function hasWorkflowWithPermissions(ElementInterface $element): bool;
}
