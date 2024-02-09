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
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer\AssetNormalizer;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class AssetTypeAdapter extends AbstractElementTypeAdapter
{
    public function __construct(
        private readonly AssetNormalizer $normalizer,
        private readonly Connection $dbConnection,
    ) {
    }

    public function supports(ElementInterface $element): bool
    {
        return $element instanceof Asset;
    }

    public function getIndexNameShortByElement(ElementInterface $element): string
    {
        return $this->getIndexNameShort();
    }

    public function getIndexNameShort(mixed $context = null): string
    {
        return IndexName::ASSET->value;
    }

    public function getElementType(): string
    {
        return ElementType::ASSET->value;
    }

    public function childrenPathRewriteNeeded(ElementInterface $element): bool
    {
        return $element instanceof Asset\Folder;
    }

    public function getNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    public function getUpdateIndexDataEvent(
        ElementInterface $element,
        array $customFields
    ): UpdateIndexDataEventInterface {
        if(!$element instanceof Asset) {
            throw new InvalidArgumentException('Element must be of type Asset');
        }

        return new UpdateIndexDataEvent($element, $customFields);
    }

    public function getRelatedItemsOnUpdateQuery(ElementInterface $element, string $operation, int $operationTime, bool $includeElement = false): ?QueryBuilder
    {
        return $this->dbConnection->createQueryBuilder()
            ->select([
                $element->getId(),
                "'" . ElementType::ASSET->value . "'",
                "'" . IndexName::ASSET->value . "'",
                "'$operation'",
                "'$operationTime'",
                '0',
            ])
            ->from('DUAL') // just a dummy query to fit into the query builder interface
            ->setMaxResults(1);
    }


}
