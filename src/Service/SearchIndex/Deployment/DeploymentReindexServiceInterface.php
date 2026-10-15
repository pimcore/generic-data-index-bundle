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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexPlan;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * @internal
 */
interface DeploymentReindexServiceInterface
{
    /**
     * @param DeploymentReindexScope[] $scopes
     *
     * @return IndexStateTarget[]
     */
    public function getTargets(array $scopes): array;

    /**
     * @throws Exception
     */
    public function plan(IndexStateTarget $target): IndexPlan;

    /**
     * Applies the plan and stores the current state once it succeeded, so a failed index is
     * picked up again by the next run.
     *
     * @return bool whether elements were added to the index queue
     *
     * @throws Exception
     */
    public function execute(IndexPlan $plan): bool;
}
