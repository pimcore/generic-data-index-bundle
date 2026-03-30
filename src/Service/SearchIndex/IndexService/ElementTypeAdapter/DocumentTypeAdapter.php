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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Document\UpdateIndexDataEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DocumentNormalizer;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class DocumentTypeAdapter extends AbstractElementTypeAdapter
{
    public function __construct(
        private readonly DocumentNormalizer $normalizer,
        private readonly Connection $dbConnection,
    ) {
    }

    public function supports(ElementInterface $element): bool
    {
        return $element instanceof Document;
    }

    public function getIndexNameShortByElement(ElementInterface $element): string
    {
        return $this->getIndexNameShort();
    }

    public function getIndexNameShort(mixed $context = null): string
    {
        return IndexName::DOCUMENT->value;
    }

    public function getElementType(): string
    {
        return ElementType::DOCUMENT->value;
    }

    public function childrenPathRewriteNeeded(ElementInterface $element): bool
    {
        return $element instanceof Document\Folder;
    }

    public function getNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    public function getUpdateIndexDataEvent(
        ElementInterface $element,
        array $customFields
    ): UpdateIndexDataEventInterface {
        if (!$element instanceof Document) {
            throw new InvalidArgumentException('Element must be of type Document');
        }

        return new UpdateIndexDataEvent($element, $customFields);
    }

    public function getRelatedItemsOnUpdateQuery(
        ElementInterface $element,
        string $operation,
        int $operationTime,
        bool $includeElement = false
    ): QueryBuilder {
        $selects = [
            (string)$element->getId() . ' AS elementId',
            "'" . ElementType::DOCUMENT->value . "' AS elementType",
            "'" . IndexName::DOCUMENT->value . "' AS elementIndexName",
            "'$operation' AS operation",
            "'$operationTime' AS operationTime",
            '0 AS dispatched',
        ];

        return $this->dbConnection->createQueryBuilder()
            ->addSelect(...$selects)
            ->from('DUAL') // just a dummy query to fit into the query builder interface
            ->setMaxResults(1);
    }
}
