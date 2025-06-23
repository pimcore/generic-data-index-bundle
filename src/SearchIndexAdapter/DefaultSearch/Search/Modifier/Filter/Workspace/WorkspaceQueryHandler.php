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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\ElementWorkspacesQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Workspace\ElementWorkspacesQueryServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Workspace\QueryServiceInterface;

/**
 * @internal
 */
final readonly class WorkspaceQueryHandler
{
    public function __construct(
        private QueryServiceInterface $workspaceQueryService,
        private ElementWorkspacesQueryServiceInterface $elementWorkspacesQueryService
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

    #[AsSearchModifierHandler]
    public function handleElementWorkspacesQuery(
        ElementWorkspacesQuery $elementWorkspacesQuery,
        SearchModifierContextInterface $context
    ): void {
        if (!$elementWorkspacesQuery->getUser()) {
            return;
        }

        $context->getSearch()->addQuery(
            $this->elementWorkspacesQueryService->getWorkspaceQuery(
                user: $elementWorkspacesQuery->getUser(),
                permission: $elementWorkspacesQuery->getPermission()
            )
        );
    }
}
