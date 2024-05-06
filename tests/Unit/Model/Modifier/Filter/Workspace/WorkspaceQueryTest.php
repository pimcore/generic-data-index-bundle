<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
            PermissionTypes::LIST->value
        );

        $this->assertSame('object', $filter->getWorkspaceType());
        $this->assertSame(1, $filter->getUser()->getId());
        $this->assertSame('list', $filter->getPermission());
    }
}
