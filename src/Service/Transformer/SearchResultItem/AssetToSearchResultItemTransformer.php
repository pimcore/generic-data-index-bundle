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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\SearchResultItemTransformationException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\AssetLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\AssetNormalizer;
use Pimcore\Model\Asset;
use Pimcore\Model\User;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

// @internal
final readonly class AssetToSearchResultItemTransformer implements AssetToSearchResultItemTransformerInterface
{
    public function __construct(
        private AssetLazyLoadingHandlerInterface $assetLazyLoadingHandler,
        private AssetNormalizer $assetNormalizer,
        private AssetSearchResultDenormalizer $assetSearchResultDenormalizer,
        private PermissionServiceInterface $permissionService,
    ) {
    }

    public function transform(Asset $asset, ?User $user = null): AssetSearchResultItem
    {
        try {
            $data = $this->assetNormalizer->normalize(
                $asset,
                null,
                SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
            );

            $searchResultItem = $this->assetSearchResultDenormalizer->denormalize(
                $data,
                AssetSearchResultItem::class,
                null,
                SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
            );

            $searchResultItem = $this->assetLazyLoadingHandler->apply($searchResultItem, $user);

            $searchResultItem->setPermissions(
                $this->permissionService->getAssetPermissions(
                    $searchResultItem,
                    $user
                )
            );

            return $searchResultItem;
        } catch (Exception|ExceptionInterface $e) {
            throw new SearchResultItemTransformationException(
                'Error transforming asset to search result item',
                0,
                $e
            );
        }
    }
}
