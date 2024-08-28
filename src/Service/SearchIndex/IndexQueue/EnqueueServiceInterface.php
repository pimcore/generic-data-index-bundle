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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\EnqueueElementsException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;

/**
 * @internal
 */
interface EnqueueServiceInterface
{
    /**
     * @throws Exception
     */
    public function enqueueByTag(Tag $tag): self;

    /**
     * @throws Exception
     */
    public function enqueueByClassDefinition(ClassDefinition $classDefinition): self;

    /**
     * @throws EnqueueElementsException
     */
    public function enqueueDataObjectFolders(): self;

    /**
     * @throws EnqueueElementsException
     */
    public function enqueueAssets(): self;

    /**
     * @throws EnqueueElementsException
     */
    public function enqueueDocuments(): self;

    /**
     * @throws \Exception
     */
    public function enqueueRelatedItemsOnUpdate(
        ElementInterface $element,
        bool $includeElement,
        string $operation
    ): void;

    public function dispatchQueueMessages(bool $synchronously = false): void;
}
