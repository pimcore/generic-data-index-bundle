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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
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

    public function getPermissions(): BasePermissions
    {
        return $this->permissions;
    }

    protected function setWorkspacePermissions(
        Workspace\Asset|Workspace\DataObject|Workspace\Document $userPermissions,
        BasePermissions $workspacePermissions
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
