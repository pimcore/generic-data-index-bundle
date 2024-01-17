<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer;

use DateTime;
use DateTimeInterface;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Element\Tag;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

abstract class AbstractElementNormalizer implements NormalizerInterface
{

    protected function extractPathLevels(ElementInterface $element): array
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

    protected function extractTagIds(ElementInterface $element): array
    {
        $tag = new Tag();
        $tags = $tag->getDao()->getTagsForElement(Service::getElementType($element), $element->getId());

        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = $tag->getId();
        }

        return $ids;
    }

    protected function formatTimestamp(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return (new DateTime())
            ->setTimestamp($timestamp)
            ->format(DateTimeInterface::ATOM);
    }
}