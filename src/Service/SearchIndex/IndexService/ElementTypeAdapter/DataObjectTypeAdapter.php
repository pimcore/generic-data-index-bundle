<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateIndexDataEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DataObjectNormalizer;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class DataObjectTypeAdapter extends AbstractElementTypeAdapter
{
    public function __construct(
        private readonly DataObjectNormalizer $normalizer,
        private readonly Connection $dbConnection,
    ) {
    }

    public function supports(ElementInterface $element): bool
    {
        return $element instanceof AbstractObject;
    }

    /**
     * @throws Exception
     */
    public function getIndexNameShortByElement(ElementInterface $element): string
    {
        $classDefinition = null;
        if ($element instanceof Concrete) {
            $classDefinition = $element->getClass();
        }

        return $this->getIndexNameShort($classDefinition);
    }

    public function getIndexNameShort(mixed $context): string
    {
        return match (true) {
            $context instanceof ClassDefinition => $context->getName(),
            $context === IndexName::DATA_OBJECT->value => $context,
            default => 'data_object_folders',
        };
    }

    public function getIndexNameByClassDefinition(ClassDefinition $classDefinition): string
    {
        return $this->searchIndexConfigService->getIndexName($classDefinition->getName());
    }

    public function getElementType(): string
    {
        return ElementType::DATA_OBJECT->value;
    }

    /**
     * @param AbstractObject $element
     */
    public function childrenPathRewriteNeeded(ElementInterface $element): bool
    {
        return $element->hasChildren(includingUnpublished: true);
    }

    public function getNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    public function getRelatedItemsOnUpdateQuery(
        ElementInterface $element,
        string $operation,
        int $operationTime,
        bool $includeElement = false
    ): ?QueryBuilder {
        if (!$element instanceof Concrete) {
            return null;
        }

        if (!$element->getClass()->getAllowInherit()) {
            if ($includeElement) {
                return $this->dbConnection->createQueryBuilder()
                    ->select([
                        $element->getId(),
                        "'" . ElementType::DATA_OBJECT->value . "'",
                        'className',
                        "'$operation'",
                        "'$operationTime'",
                        '0',
                    ])
                    ->from('objects') // just a dummy query to fit into the query builder interface
                    ->where('id = :id')
                    ->setMaxResults(1)
                    ->setParameter('id', $element->getId());
            }

            return null;
        }

        $select = $this->dbConnection->createQueryBuilder()
            ->select([
                'id',
                "'" . ElementType::DATA_OBJECT->value . "'",
                'className',
                "'$operation'",
                "'$operationTime'",
                '0',
            ])
            ->from('objects')
            ->where('classId = :classId')
            ->andWhere('path LIKE :path')
            ->setParameters([
                'classId' => $element->getClassId(),
                'path' => $element->getRealFullPath() . '/%',
            ]);

        if ($includeElement) {
            $select
                ->orWhere('id = :id')
                ->setParameter('id', $element->getId());
        }

        return $select;
    }

    public function getUpdateIndexDataEvent(
        ElementInterface $element,
        array $customFields
    ): UpdateIndexDataEventInterface {
        if (!$element instanceof Concrete) {
            throw new InvalidArgumentException('Element must be instance of ' . Concrete::class);
        }

        return new UpdateIndexDataEvent($element, $customFields);
    }
}
