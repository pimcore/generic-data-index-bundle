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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\ElementLockServiceInterface;
use Pimcore\Event\ElementEvents;
use Pimcore\Event\Model\ElementEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final readonly class LockElementSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Installer $installer,
        private ElementLockServiceInterface $lockService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ElementEvents::POST_ELEMENT_UNLOCK_PROPAGATE => 'updateUnlockPropagate',
        ];
    }

    /**
     * @throws Exception
     */
    public function updateUnlockPropagate(ElementEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->lockService->unlockPropagate($event->getElement());
    }
}
