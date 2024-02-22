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
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final class AssetWorkspace extends AbstractWorkspace
{
    public const WORKSPACE_TYPE = 'asset';

    public function __construct(
        Workspace\Asset $assetPermissions
    ) {
        $this->setWorkspacePermissions(
            userPermissions: $assetPermissions,
            workspacePermissions: new AssetPermissions()
        );

        parent::__construct($assetPermissions->getCpath());
    }
}