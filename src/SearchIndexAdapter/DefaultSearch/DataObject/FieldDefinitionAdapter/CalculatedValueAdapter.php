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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;

/**
 * Maps calculated value fields based on their configured element type instead of
 * always falling back to a text mapping, so that boolean/numeric/date calculated
 * values stay filterable and aggregatable in the search index.
 *
 * @internal
 */
final class CalculatedValueAdapter extends AbstractAdapter
{
    private const ELEMENT_TYPE_BOOLEAN = 'boolean';

    private const ELEMENT_TYPE_NUMERIC = 'numeric';

    private const ELEMENT_TYPE_DATE = 'date';

    public function __construct(
        protected SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected FieldDefinitionServiceInterface $fieldDefinitionService,
        private readonly IndexMappingServiceInterface $indexMappingService,
    ) {
        parent::__construct(
            $searchIndexConfigService,
            $fieldDefinitionService
        );
    }

    public function getIndexMapping(): array
    {
        return match ($this->getElementType()) {
            self::ELEMENT_TYPE_BOOLEAN => [
                'type' => AttributeType::BOOLEAN->value,
            ],
            self::ELEMENT_TYPE_NUMERIC => [
                'type' => AttributeType::FLOAT->value,
            ],
            self::ELEMENT_TYPE_DATE => [
                'type' => AttributeType::DATE->value,
                'format' => 'strict_date_time_no_millis',
            ],
            default => $this->indexMappingService->getMappingForTextKeyword(
                $this->searchIndexConfigService->getSearchAnalyzerAttributes()
            ),
        };
    }

    public function normalize(mixed $value): mixed
    {
        return match ($this->getElementType()) {
            self::ELEMENT_TYPE_BOOLEAN => $this->normalizeBoolean($value),
            self::ELEMENT_TYPE_NUMERIC => is_numeric($value) ? (float) $value : null,
            self::ELEMENT_TYPE_DATE => $value instanceof DateTimeInterface
                ? $value->format(DateTimeInterface::ATOM)
                : null,
            default => $this->normalizeText($value),
        };
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        return (bool) $value;
    }

    private function normalizeText(mixed $value): mixed
    {
        if (is_string($value) && $value !== '') {
            return preg_replace("/src=(['\"])data:[^;]+;base64,.+?\\1/", '', $value);
        }

        return parent::normalize($value);
    }

    private function getElementType(): ?string
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof CalculatedValue) {
            return null;
        }

        return $fieldDefinition->getElementType();
    }
}
