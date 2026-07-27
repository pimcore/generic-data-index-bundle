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

namespace Pimcore\Bundle\GenericDataIndexBundle\MessageHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateSiblingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexElementIndexServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class UpdateSiblingsHandler
{
    public function __construct(
        private IndexElementIndexServiceInterface $indexElementIndexService,
        private ElementServiceInterface $elementService
    ) {
    }

    public function __invoke(UpdateSiblingsMessage $message): void
    {
        $element = $this->elementService->getElementByType($message->getElementId(), $message->getElementType());

        if ($element === null) {
            return;
        }

        $this->indexElementIndexService->updateSiblings($element, $message->getElementType());

        if ($message->getResetChildrenIndexBy()
            && $element instanceof AbstractObject
            && $element->getChildrenSortBy() === AbstractObject::OBJECT_CHILDREN_SORT_BY_INDEX
        ) {
            $this->indexElementIndexService->resetChildrenIndexBy($element);
        }
    }
}
