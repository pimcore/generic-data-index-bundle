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
