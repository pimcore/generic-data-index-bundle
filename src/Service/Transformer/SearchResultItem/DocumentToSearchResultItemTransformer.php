<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\SearchResultItemTransformationException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\AssetLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DataObjectLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DocumentLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DataObjectSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DocumentSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\AssetNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DataObjectNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DocumentNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem\AssetToSearchResultItemTransformerInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;
use Pimcore\Model\User;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/***
 * @internal
 */
final readonly class DocumentToSearchResultItemTransformer implements DocumentToSearchResultItemTransformerInterface
{
    public function __construct(
        private DocumentLazyLoadingHandlerInterface $documentLazyLoadingHandler,
        private DocumentNormalizer $documentNormalizer,
        private DocumentSearchResultDenormalizer $documentSearchResultDenormalizer,
        private PermissionServiceInterface $permissionService,
    )
    {
    }

    public function transform(Document $document, ?User $user = null): DocumentSearchResultItem
    {
        try {
            $data = $this->documentNormalizer->normalize(
                $document,
                null,
                SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
            );

            $searchResultItem = $this->documentSearchResultDenormalizer->denormalize(
                $data,
                DataObjectSearchResultItem::class,
                null,
                SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
            );

            $searchResultItem = $this->documentLazyLoadingHandler->apply($searchResultItem, $user);

            $searchResultItem->setPermissions(
                $this->permissionService->getDocumentPermissions(
                    $searchResultItem,
                    $user
                )
            );

            return $searchResultItem;
        } catch (Exception|ExceptionInterface $e) {
            throw new SearchResultItemTransformationException(
                'Error transforming document to search result item',
                0,
                $e
            );
        }
    }
}