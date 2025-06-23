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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidPermissionTypeException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
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
            workspacePermissions: new DataObjectPermissions()
        );

        parent::__construct($objectPermissions->getCpath());
    }

    protected function setWorkspacePermissions(
        Workspace\Asset|Workspace\DataObject|Workspace\Document $userPermissions,
        BasePermissions $workspacePermissions
    ): void {
        if (!$workspacePermissions instanceof DataObjectPermissions) {
            throw new InvalidPermissionTypeException('Invalid Permission object provided');
        }

        parent::setWorkspacePermissions($userPermissions, $workspacePermissions);
        $workspacePermissions->setLocalizedEdit($userPermissions->getLEdit());
        $workspacePermissions->setLocalizedView($userPermissions->getLView());
    }
}
