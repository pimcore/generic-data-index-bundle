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
        $tag = new Tag();
        $tags = $tag->getDao()->getTagsForElement(Service::getElementType($element), $element->getId());

        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = $tag->getId();
        }

        return $ids;
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
