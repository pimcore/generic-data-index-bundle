<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Workspace\QueryServiceInterface;

/**
 * @internal
 */
final readonly class WorkspaceQueryHandler
{
    public function __construct(
        private QueryServiceInterface $workspaceQueryService
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleWorkspaceQuery(
        WorkspaceQuery $workspaceQuery,
        SearchModifierContextInterface $context
    ): void {
        if (!$workspaceQuery->getUser()) {
            return;
        }

        $context->getSearch()->addQuery(
            $this->workspaceQueryService->getWorkspaceQuery(
                workspaceType: $workspaceQuery->getWorkspaceType(),
                user: $workspaceQuery->getUser(),
                permission: $workspaceQuery->getPermission()
            )
        );
    }
}
