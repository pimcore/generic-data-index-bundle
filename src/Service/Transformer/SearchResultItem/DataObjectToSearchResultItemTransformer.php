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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DataObjectLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DataObjectSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DataObjectNormalizer;
use Pimcore\Model\DataObject;
use Pimcore\Model\User;

// @internal
final readonly class DataObjectToSearchResultItemTransformer implements DataObjectToSearchResultItemTransformerInterface
{
    public function __construct(
        private DataObjectLazyLoadingHandlerInterface $lazyLoadingHandler,
        private DataObjectNormalizer $normalizer,
        private DataObjectSearchResultDenormalizer $denormalizer,
        private PermissionServiceInterface $permissionService,
    ) {
    }

    public function transform(DataObject $dataObject, ?User $user = null): DataObjectSearchResultItem
    {
        try {
            $context = SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext();

            $searchResultItem = $this->denormalizer->denormalize(
                $this->normalizer->normalize($dataObject, null, $context),
                DataObjectSearchResultItem::class,
                null,
                $context
            );

            return $this->lazyLoadingHandler
                ->apply($searchResultItem, $user)
                ->setPermissions($this->permissionService->getDataObjectPermissions($searchResultItem, $user));

        } catch (Exception $e) {
            throw new SearchResultItemTransformationException(
                'Error transforming data object to search result item',
                0,
                $e
            );
        }
    }
}
