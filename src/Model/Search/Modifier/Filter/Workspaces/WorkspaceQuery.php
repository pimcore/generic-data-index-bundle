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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Model\User;

final readonly class WorkspaceQuery implements SearchModifierInterface
{
    public function __construct(
        private string $workspaceType,
        private ?User $user = null,
        private ?string $permission = null
    ) {
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getPermission(): ?string
    {
        return $this->permission ?? PermissionTypes::LIST->value;
    }

    public function getWorkspaceType(): string
    {
        return $this->workspaceType;
    }
}
