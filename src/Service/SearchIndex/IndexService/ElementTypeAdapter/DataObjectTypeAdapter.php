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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateIndexDataEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer\DataObjectNormalizer;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DataObjectTypeAdapter extends AbstractElementTypeAdapter
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
        if($element instanceof Concrete) {
            $classDefinition = $element->getClass();
        }

        return $this->getIndexNameShort($classDefinition);
    }

    public function getIndexNameShort(mixed $context): string
    {
        if ($context instanceof ClassDefinition) {
            return $context->getName();
        }

        return 'data_object_folders';
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
        if(!$element instanceof Concrete) {
            return null;
        }

        if(!$element->getClass()->getAllowInherit()) {
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
