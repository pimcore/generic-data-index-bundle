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

/**
 * The locale is the language node the filtered key is indexed under, and it stays the caller's
 * choice: it is not resolved from the key, where the mapping and the document resolve it per key.
 * A group can therefore hold keys this filter has to be given different locales for - the default
 * for a key that is not localized, the language for one that is - and a locale the key is not
 * indexed under matches nothing instead of failing, so the empty result reads like an absent value.
 *
 * The service below answers the locale of a key id with the resolution the mapping and the document
 * use themselves, and is what a caller that does not already know a key's languages should ask.
 *
 * @see \Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleServiceInterface
 */
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
