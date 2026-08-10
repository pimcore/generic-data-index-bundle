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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\Concrete;

/**
 * @internal
 */
final class CalculatedValueQueryStoreService implements CalculatedValueQueryStoreServiceInterface
{
    use LoggerAwareTrait;

    private const CACHE_LIMIT = 200;

    /**
     * Query rows per "classId:objectId(:language)". Elements are processed one at a
     * time, so one fetch per element (and language) serves all its calculated fields.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $rowCache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getValue(Concrete $dataObject, CalculatedValue $fieldDefinition): ?string
    {
        $row = $this->getRow(
            'object_query_' . $dataObject->getClassId(),
            $this->getCalculatedFieldNames($dataObject, localized: false),
            $dataObject,
            $dataObject->getClassId() . ':' . $dataObject->getId()
        );

        return $this->toValue($row, $fieldDefinition->getName());
    }

    public function getLocalizedValue(
        Concrete $dataObject,
        CalculatedValue $fieldDefinition,
        string $language
    ): ?string {
        $row = $this->getRow(
            'object_localized_query_' . $dataObject->getClassId() . '_' . $language,
            $this->getCalculatedFieldNames($dataObject, localized: true),
            $dataObject,
            $dataObject->getClassId() . ':' . $dataObject->getId() . ':' . $language
        );

        return $this->toValue($row, $fieldDefinition->getName());
    }

    /**
     * @param string[] $fieldNames
     *
     * @return array<string, mixed>
     */
    private function getRow(string $table, array $fieldNames, Concrete $dataObject, string $cacheKey): array
    {
        if (isset($this->rowCache[$cacheKey])) {
            return $this->rowCache[$cacheKey];
        }

        if (count($this->rowCache) >= self::CACHE_LIMIT) {
            $this->rowCache = [];
        }

        $row = [];
        if ($fieldNames !== []) {
            $columns = implode(', ', array_map(
                fn (string $name) => $this->connection->quoteIdentifier($name),
                $fieldNames
            ));

            try {
                $result = $this->connection->fetchAssociative(
                    sprintf(
                        'SELECT %s FROM %s WHERE %s = ?',
                        $columns,
                        $this->connection->quoteIdentifier($table),
                        $this->connection->quoteIdentifier('oo_id')
                    ),
                    [$dataObject->getId()]
                );
                $row = $result ?: [];
            } catch (DBALException $e) {
                // Missing table/column (e.g. field added but class not yet rebuilt): the
                // affected values degrade to null instead of failing the whole element.
                $this->logger->warning(sprintf(
                    'Could not read calculated field values from "%s" for object %d: %s',
                    $table,
                    $dataObject->getId(),
                    $e->getMessage()
                ));
            }
        }

        return $this->rowCache[$cacheKey] = $row;
    }

    /**
     * @return string[]
     */
    private function getCalculatedFieldNames(Concrete $dataObject, bool $localized): array
    {
        $fieldDefinitions = $localized
            ? $this->getLocalizedFieldDefinitions($dataObject)
            : $dataObject->getClass()->getFieldDefinitions();

        $names = [];
        foreach ($fieldDefinitions as $fieldDefinition) {
            if ($fieldDefinition instanceof CalculatedValue) {
                $names[] = $fieldDefinition->getName();
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function getLocalizedFieldDefinitions(Concrete $dataObject): array
    {
        $localizedFields = $dataObject->getClass()->getFieldDefinition('localizedfields');

        return $localizedFields instanceof Localizedfields ? $localizedFields->getFieldDefinitions() : [];
    }

    private function toValue(array $row, string $fieldName): ?string
    {
        $value = $row[$fieldName] ?? null;

        return $value === null ? null : (string) $value;
    }
}
