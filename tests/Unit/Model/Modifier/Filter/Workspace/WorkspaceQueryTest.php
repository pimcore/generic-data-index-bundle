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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Filter\Workspace;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Model\User;

/**
 * @internal
 */
final class WorkspaceQueryTest extends Unit
{
    public function testGetId(): void
    {
        $filter = new WorkspaceQuery(
            DataObjectWorkspace::WORKSPACE_TYPE,
            (new User())->setId(1),
            PermissionTypes::VIEW->value
        );

        $this->assertSame('object', $filter->getWorkspaceType());
        $this->assertSame(1, $filter->getUser()->getId());
        $this->assertSame('view', $filter->getPermission());
    }
}
