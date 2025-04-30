<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Model\User\Workspace\Asset as WorkspaceAsset;
use Pimcore\Model\User\Workspace\DataObject as WorkspaceDataObject;
use Pimcore\Model\User\Workspace\Document as WorkspaceDocument;

class Service extends Module
{
    /**
     * @throws InvalidArgumentException
     */
    public function getWorkspace(
        string $path,
        array $permissions,
        string $type
    ): WorkspaceAsset|WorkspaceDataObject|WorkspaceDocument {
        $workspace = match(true) {
            $type === AssetWorkspace::WORKSPACE_TYPE => new WorkspaceAsset(),
            $type === DataObjectWorkspace::WORKSPACE_TYPE => new WorkspaceDataObject(),
            $type === DocumentWorkspace::WORKSPACE_TYPE => new WorkspaceDocument(),
            default => throw new InvalidArgumentException('wrong workspace type provided')
        };
        $workspace->setCpath($path);
        foreach ($permissions as $permission => $value) {
            $setter = 'set' . ucfirst($permission);
            $workspace->$setter($value);
        }

        return $workspace;
    }
}
