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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\MessageHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateSiblingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\UpdateSiblingsHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexElementIndexServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class UpdateSiblingsHandlerTest extends Unit
{
    public function testUpdateSiblingsCalledForDataObject(): void
    {
        $element = $this->makeEmpty(AbstractObject::class, [
            'getChildrenSortBy' => 'key',
        ]);

        $handler = new UpdateSiblingsHandler(
            $this->makeEmpty(IndexElementIndexServiceInterface::class, [
                'updateSiblings' => Expected::once(),
                'resetChildrenIndexBy' => Expected::never(),
            ]),
            $this->makeEmpty(ElementServiceInterface::class, [
                'getElementByType' => $element,
            ])
        );

        $handler(new UpdateSiblingsMessage(1, ElementType::DATA_OBJECT->value, true));
    }

    public function testResetChildrenIndexByCalledWhenSortByIndex(): void
    {
        $element = $this->makeEmpty(AbstractObject::class, [
            'getChildrenSortBy' => AbstractObject::OBJECT_CHILDREN_SORT_BY_INDEX,
        ]);

        $handler = new UpdateSiblingsHandler(
            $this->makeEmpty(IndexElementIndexServiceInterface::class, [
                'updateSiblings' => Expected::once(),
                'resetChildrenIndexBy' => Expected::once(),
            ]),
            $this->makeEmpty(ElementServiceInterface::class, [
                'getElementByType' => $element,
            ])
        );

        $handler(new UpdateSiblingsMessage(1, ElementType::DATA_OBJECT->value, true));
    }

    public function testResetChildrenIndexByNotCalledForDocuments(): void
    {
        $element = $this->makeEmpty(Document::class);

        $handler = new UpdateSiblingsHandler(
            $this->makeEmpty(IndexElementIndexServiceInterface::class, [
                'updateSiblings' => Expected::once(),
                'resetChildrenIndexBy' => Expected::never(),
            ]),
            $this->makeEmpty(ElementServiceInterface::class, [
                'getElementByType' => $element,
            ])
        );

        $handler(new UpdateSiblingsMessage(1, ElementType::DOCUMENT->value, false));
    }

    public function testNullElementSkipsProcessing(): void
    {
        $handler = new UpdateSiblingsHandler(
            $this->makeEmpty(IndexElementIndexServiceInterface::class, [
                'updateSiblings' => Expected::never(),
                'resetChildrenIndexBy' => Expected::never(),
            ]),
            $this->makeEmpty(ElementServiceInterface::class, [
                'getElementByType' => null,
            ])
        );

        $handler(new UpdateSiblingsMessage(999, ElementType::DATA_OBJECT->value, true));
    }

    public function testResetChildrenIndexByNotCalledWhenFlagIsFalse(): void
    {
        $element = $this->makeEmpty(AbstractObject::class, [
            'getChildrenSortBy' => AbstractObject::OBJECT_CHILDREN_SORT_BY_INDEX,
        ]);

        $handler = new UpdateSiblingsHandler(
            $this->makeEmpty(IndexElementIndexServiceInterface::class, [
                'updateSiblings' => Expected::once(),
                'resetChildrenIndexBy' => Expected::never(),
            ]),
            $this->makeEmpty(ElementServiceInterface::class, [
                'getElementByType' => $element,
            ])
        );

        $handler(new UpdateSiblingsMessage(1, ElementType::DATA_OBJECT->value, false));
    }
}
