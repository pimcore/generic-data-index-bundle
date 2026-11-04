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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\LockedElementsCacheInterface;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\DocumentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Element saves and deletes can change lock values or paths, so lock checks after them must not use
 * the locked elements loaded before.
 *
 * @internal
 */
final readonly class LockedElementsCacheResetSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LockedElementsCacheInterface $lockedElementsCache
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AssetEvents::POST_ADD => 'resetCache',
            AssetEvents::POST_UPDATE => 'resetCache',
            AssetEvents::POST_DELETE => 'resetCache',
            DataObjectEvents::POST_ADD => 'resetCache',
            DataObjectEvents::POST_UPDATE => 'resetCache',
            DataObjectEvents::POST_DELETE => 'resetCache',
            DocumentEvents::POST_ADD => 'resetCache',
            DocumentEvents::POST_UPDATE => 'resetCache',
            DocumentEvents::POST_DELETE => 'resetCache',
        ];
    }

    public function resetCache(): void
    {
        $this->lockedElementsCache->reset();
    }
}
