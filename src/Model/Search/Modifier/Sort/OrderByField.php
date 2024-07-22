<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class OrderByField implements SearchModifierInterface
{
    public function __construct(
        private string $fieldName,
        private SortDirection $direction = SortDirection::ASC,
        private bool $enablePqlFieldNameResolution = true,
    ) {
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getDirection(): SortDirection
    {
        return $this->direction;
    }

    public function isPqlFieldNameResolutionEnabled(): bool
    {
        return $this->enablePqlFieldNameResolution;
    }
}