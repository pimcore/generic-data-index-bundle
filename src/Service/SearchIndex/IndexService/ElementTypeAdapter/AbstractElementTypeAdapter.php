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

use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
abstract class AbstractElementTypeAdapter
{
    protected SearchIndexConfigServiceInterface $searchIndexConfigService;

    abstract public function supports(ElementInterface $element): bool;

    public function getAliasIndexNameByElement(ElementInterface $element): string
    {
        return $this->searchIndexConfigService->getIndexName(
            $this->getIndexNameShortByElement($element)
        );
    }

    public function getAliasIndexName(mixed $context = null): string
    {
        return $this->searchIndexConfigService->getIndexName(
            $this->getIndexNameShort($context)
        );
    }

    abstract public function getIndexNameShort(mixed $context): string;

    abstract public function getIndexNameShortByElement(ElementInterface $element): string;

    abstract public function getElementType(): string;

    abstract public function childrenPathRewriteNeeded(ElementInterface $element): bool;

    abstract public function getNormalizer(): NormalizerInterface;

    abstract public function getUpdateIndexDataEvent(
        ElementInterface $element,
        array $customFields
    ): UpdateIndexDataEventInterface;

    /**
     * @throws Exception
     */
    public function getRelatedItemsOnUpdateQuery(
        ElementInterface $element,
        string $operation,
        int $operationTime,
        bool $includeElement = false
    ): ?QueryBuilder {
        return null;
    }

    #[Required]
    public function setSearchIndexConfigService(
        SearchIndexConfigServiceInterface $searchIndexConfigService
    ): void {
        $this->searchIndexConfigService = $searchIndexConfigService;
    }
}
