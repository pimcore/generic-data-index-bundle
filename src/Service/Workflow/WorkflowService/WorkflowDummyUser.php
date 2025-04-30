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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowService;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
final readonly class WorkflowDummyUser implements UserInterface
{
    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials(): void
    {
        // not needed for dummy user
    }

    public function getUserIdentifier(): string
    {
        return 'workflow dummy user';
    }
}
