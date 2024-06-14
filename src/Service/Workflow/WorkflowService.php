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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow;

use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowService\WorkflowDummyUser;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Workflow\Manager;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal
 */
final readonly class WorkflowService implements WorkflowServiceInterface
{
    public function __construct(
        private Manager $workflowManager,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public function hasWorkflowWithPermissions(ElementInterface $element): bool
    {
        $resetToken = false;
        if (!$this->tokenStorage->getToken()) {
            $this->tokenStorage->setToken(new PreAuthenticatedToken(new WorkflowDummyUser(), 'main'));
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
