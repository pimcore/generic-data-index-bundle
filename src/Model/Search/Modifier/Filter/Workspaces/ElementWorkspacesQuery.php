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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Model\User;

final readonly class ElementWorkspacesQuery implements SearchModifierInterface
{
    public function __construct(
        private ?User $user = null,
        private ?string $permission = null
    ) {
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getPermission(): string
    {
        return $this->permission ?? PermissionTypes::LIST->value;
    }
}
