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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\NumberFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\FullTextSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\WildcardSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;

final readonly class ClassificationStoreFilter implements SearchModifierInterface
{
    public function __construct(
        private string $fieldName,
        private string $group,
        private BooleanFilter|DateFilter|FullTextSearch|IntegerFilter|MultiSelectFilter|BooleanMultiSelectFilter|
        NumberFilter|NumberRangeFilter|WildcardSearch|TimeFilter $subModifier,
        private string $locale = MappingProperty::NOT_LOCALIZED_KEY,
    ) {
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getSubModifier(): BooleanFilter|DateFilter|FullTextSearch|IntegerFilter|
    MultiSelectFilter|BooleanMultiSelectFilter|NumberFilter|NumberRangeFilter|WildcardSearch|TimeFilter
    {
        return $this->subModifier;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
