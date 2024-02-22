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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidPermissionTypeException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final class DataObjectWorkspace extends AbstractWorkspace
{
    public const WORKSPACE_TYPE = 'object';

    public function __construct(
        Workspace\DataObject $objectPermissions
    ) {
        $this->setWorkspacePermissions(
            userPermissions: $objectPermissions,
            workspacePermissions: new DataObjectPermission()
        );

        parent::__construct($objectPermissions->getCpath());
    }

    protected function setWorkspacePermissions(
        Workspace\Asset|Workspace\DataObject|Workspace\Document $userPermissions,
        BasePermissions $workspacePermissions
    ): void {
        if (!$workspacePermissions instanceof DataObjectPermission) {
            throw new InvalidPermissionTypeException('Invalid Permission object provided');
        }

        parent::setWorkspacePermissions($userPermissions, $workspacePermissions);
        $workspacePermissions->setLocalizedEdit($userPermissions->getLEdit());
        $workspacePermissions->setLocalizedView($userPermissions->getLView());
    }
}
