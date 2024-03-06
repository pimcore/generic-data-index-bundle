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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\ValueObject\Collection\ArrayOfStrings;
use ValueError;

abstract class AbstractAdapter implements AdapterInterface
{
    public function __construct(
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
    }

    private string $type;

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    abstract public function getIndexMapping(): array;

    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param OpenSearchSearchInterface $adapterSearch
     *
     * @throws InvalidArgumentException
     */
    public function applySearchFilter(AssetMetaDataFilter $filter, AdapterSearchInterface $adapterSearch): void
    {
        if ($filter->getType() !== $this->getType()) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s does not support filter type "%s" for filter "%s"',
                    static::class,
                    $filter->getType(),
                    $filter->getName()
                )
            );
        }

        $value = $filter->getData();

        $query = null;
        if ($this->isValidScalar($value)) {
            $query = new TermFilter($this->getSearchFilterFieldPath($filter), $value);
        } elseif(is_array($value)) {
            try {
                $this->validateArray($value);
            } catch (ValueError) {
                $this->throwInvalidFilterValueArgumentException($value, $filter);
            }
            $value = array_unique($value);
            $query = new TermsFilter($this->getSearchFilterFieldPath($filter), $value);
        }

        if ($query === null) {
            $this->throwInvalidFilterValueArgumentException($value, $filter);
        }

        $adapterSearch->addQuery(
            $query
        );
    }

    public function getSearchFilterAggregation(AssetMetaDataAggregation $aggregation): ?Aggregation
    {
        return null;
    }

    protected function isValidScalar(mixed $value): bool
    {
        return is_string($value);
    }

    /**
     * @throws ValueError
     */
    protected function validateArray(array $value): void
    {
        new ArrayOfStrings($value);
    }

    protected function getSearchFilterFieldPath(AssetMetaDataFilter|AssetMetaDataAggregation $filter): string
    {
        return implode('.',
            [
                FieldCategory::STANDARD_FIELDS->value,
                $filter->getName(),
                $filter->getLanguage() ?? MappingProperty::NOT_LOCALIZED_KEY,
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function throwInvalidFilterValueArgumentException(mixed $value, AssetMetaDataFilter $filter): void
    {
        throw new InvalidArgumentException(
            sprintf(
                'Unsupported value type "%s" for filter "%s"',
                gettype($value),
                $filter->getName()
            )
        );
    }
}
