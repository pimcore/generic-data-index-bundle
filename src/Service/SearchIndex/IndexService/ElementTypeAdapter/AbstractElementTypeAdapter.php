<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\MappingHandler\MappingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractElementTypeAdapter
{
    protected SearchIndexConfigService $searchIndexConfigService;


    abstract public function supports(ElementInterface $element): bool;
    public function getIndexName(ElementInterface $element): string
    {
        return $this->searchIndexConfigService->getIndexName(
            $this->getIndexNameShort($element)
        );
    }

    abstract public function getIndexNameShort(ElementInterface $element): string;
    abstract public function getElementType(): string;

    abstract public function childrenPathRewriteNeeded(ElementInterface $element): bool;

    abstract public function getNormalizer(): NormalizerInterface;
    abstract public function getMappingHandler(): MappingHandlerInterface;

    /**
     * @throws Exception
     */
    public function getRelatedItemsOnUpdateQuery(
        ElementInterface $element,
        string $operation,
        int $operationTime,
        bool $includeElement = false
    ): ?QueryBuilder
    {
        return null;
    }

    #[Required]
    public function setSearchIndexConfigService(SearchIndexConfigService $searchIndexConfigService): void
    {
        $this->searchIndexConfigService = $searchIndexConfigService;
    }
}