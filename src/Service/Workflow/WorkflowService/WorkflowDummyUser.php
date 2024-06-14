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
