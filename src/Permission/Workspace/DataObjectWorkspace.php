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

use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;
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
        AssetPermissions|DataObjectPermission|DocumentPermission $workspacePermissions
    ): void
    {

        parent::setWorkspacePermissions($userPermissions, $workspacePermissions);
        $workspacePermissions->setLocalizedEdit($userPermissions->getLEdit());
        $workspacePermissions->setLocalizedView($userPermissions->getLView());
        $workspacePermissions->setLayouts($userPermissions->getLayouts());
    }
}