<?php
declare(strict_types=1);

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
    }

    public function getUserIdentifier(): string
    {
        return 'workflow dummy user';
    }

}