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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Model\User;

/**
 * @internal
 */
interface ElementWorkspacesQueryServiceInterface
{
    /**
     * Returns a query which respects the workspace permissions for all element types.
     */
    public function getWorkspaceQuery(?User $user, string $permission): BoolQuery;
}
