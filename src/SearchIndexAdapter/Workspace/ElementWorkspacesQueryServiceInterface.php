<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
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