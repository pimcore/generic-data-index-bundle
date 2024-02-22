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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\WorkspaceNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;

/**
 * @internal
 */
interface WorkspaceServiceInterface
{
    /**
     * @throws WorkspaceNotFoundException
     */
    public function getRelevantWorkspaces(
        array $userWorkspaces,
        string $assetPath
    ): array;

    public function getRoleWorkspaces(array $roleIds, string $type): array;

    public function getDeepestWorkspace(array $workspaces): WorkspaceInterface;
}