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
        bool $includeElement
    ): void;

    public function dispatchQueueMessages(bool $synchronously = false): void;
}
