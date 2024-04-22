<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;

/**
 * @internal
 */
final readonly class IndexNameResolver implements IndexNameResolverInterface
{
    public function __construct(
        private AssetTypeAdapter $assetTypeAdapter,
        private DataObjectTypeAdapter $dataObjectTypeAdapter,
        private DocumentTypeAdapter $documentTypeAdapter,
    )
    {
    }

    public function resolveIndexName(SearchInterface $search): string
    {
        if ($search instanceof AssetSearch) {
            return $this->assetTypeAdapter->getAliasIndexName();
        }

        if ($search instanceof DataObjectSearch) {
            return $this->dataObjectTypeAdapter->getAliasIndexName($search->getClassDefinition());
        }

        if ($search instanceof DocumentSearch) {
            return $this->documentTypeAdapter->getAliasIndexName();
        }

        throw new InvalidArgumentException('Unsupported search type: ' . get_class($search));
    }

}