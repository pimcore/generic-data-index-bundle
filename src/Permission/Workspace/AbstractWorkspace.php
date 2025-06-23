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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
abstract class AbstractWorkspace implements WorkspaceInterface
{
    private readonly string $path;

    protected mixed $permissions = null;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPermissions(): AssetPermissions|DataObjectPermissions|DocumentPermissions
    {
        return $this->permissions;
    }

    protected function setWorkspacePermissions(
        Workspace\Asset|Workspace\DataObject|Workspace\Document $userPermissions,
        AssetPermissions|DataObjectPermissions|DocumentPermissions $workspacePermissions
    ): void {
        $properties = $workspacePermissions->getClassProperties();
        foreach ($properties as $property => $value) {
            $setter = 'set' . ucfirst($property);
            $getter = 'get' . ucfirst($property);
            if (method_exists($userPermissions, $getter) && method_exists($workspacePermissions, $setter)) {
                $workspacePermissions->$setter($userPermissions->$getter());
            }
        }
        $this->permissions = $workspacePermissions;
    }
}
