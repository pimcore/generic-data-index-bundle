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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\QueryServiceInterface;

/**
 * @internal
 */
final class WorkspaceQueryHandler
{
    public function __construct(
        private readonly QueryServiceInterface $workspaceQueryService
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
