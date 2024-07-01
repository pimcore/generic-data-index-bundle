<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\AssetLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\AssetNormalizer;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;
use Pimcore\Model\User;

/***
 * @internal
 */
final readonly class AssetToSearchResultItemTransformer implements AssetToSearchResultItemTransformerInterface
{
    public function __construct(
        private AssetLazyLoadingHandlerInterface $assetLazyLoadingHandler,
        private AssetNormalizer $assetNormalizer,
        private AssetSearchResultDenormalizer $assetSearchResultDenormalizer,
        private PermissionServiceInterface $permissionService,
    )
    {
    }

    public function transform(Asset $asset, ?User $user = null): AssetSearchResultItem
    {
        $data = $this->assetNormalizer->normalize(
            $asset,
            null,
            SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
        );

        $result = $this->assetSearchResultDenormalizer->denormalize(
            $data,
            AssetSearchResultItem::class,
            null,
            SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
        );

        $result = $this->assetLazyLoadingHandler->apply($result, $user);

        $result->setPermissions(
            $this->permissionService->getAssetPermissions(
                $result,
                $user
            )
        );

        return $result;
    }
}