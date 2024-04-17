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

namespace Pimcore\Bundle\GenericDataIndexBundle\Traits;

use DateTime;
use DateTimeInterface;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Element\Tag;

/**
 * @internal
 */
trait ElementNormalizerTrait
{
    private function extractPathLevels(ElementInterface $element): array
    {
        $path = $element->getType() === 'folder' ? $element->getRealFullPath() : $element->getPath();
        $levels = explode('/', rtrim($path, '/'));
        unset($levels[0]);

        $result = [];
        foreach ($levels as $level => $name) {
            $result[] = [
                'level' => $level,
                'name' => $name,
            ];
        }

        return $result;
    }

    private function extractTagIds(ElementInterface $element): array
    {
        $ids = [];
        $tags = $this->getTagsByElement($element);
        foreach ($tags as $tag) {
            $ids[] = $tag->getId();
        }

        return $ids;
    }

    private function extractParentTagIds(ElementInterface $element): array
    {
        $ids = [];
        $tags = $this->getTagsByElement($element);

        foreach ($tags as $tag) {
            $ids = $this->getAllTagParentsIds($tag, $ids);
        }

        return array_unique($ids);
    }

    private function getAllTagParentsIds(
        Tag $tag,
        array $parentTagIds
    ): array {
        $parentId = $tag->getParentId();
        $parent = $tag->getParent();

        if ($parent === null ||
            $parentId === 0 ||
            in_array($parentId, $parentTagIds, true)
        ) {
            return $parentTagIds;
        }

        $parentTagIds[] = $parentId;

        return $this->getAllTagParentsIds($parent, $parentTagIds);
    }

    private function getTagsByElement(ElementInterface $element): array
    {
        $tag = new Tag();

        return $tag->getDao()->getTagsForElement(
            Service::getElementType($element), $element->getId()
        );

    }

    private function formatTimestamp(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return (new DateTime())
            ->setTimestamp($timestamp)
            ->format(DateTimeInterface::ATOM);
    }
}
