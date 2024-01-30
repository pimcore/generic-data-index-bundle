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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow;

use Pimcore\Model\Element\ElementInterface;
use Pimcore\Workflow\Manager;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal
 */
final class WorkflowService implements WorkflowServiceInterface
{
    public function __construct(
        private readonly Manager $workflowManager,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function hasWorkflowWithPermissions(ElementInterface $element): bool
    {
        $resetToken = false;
        if (!$this->tokenStorage->getToken()) {
            $this->tokenStorage->setToken(new NullToken());
            $resetToken = true;
        }

        $workflows = $this->workflowManager->getAllWorkflowsForSubject($element);

        if ($resetToken) {
            $this->tokenStorage->setToken(null);
        }

        foreach ($workflows as $workflow) {
            $places = $this->workflowManager->getPlaceConfigsByWorkflowName($workflow->getName());
            foreach ($places as $place) {
                $permissions = $place->getPlaceConfigArray()['permissions'] ?? [];
                if (count($permissions) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
