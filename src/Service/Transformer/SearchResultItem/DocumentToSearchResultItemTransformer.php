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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DocumentLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DocumentSearchResultDenormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DocumentNormalizer;
use Pimcore\Model\Document;
use Pimcore\Model\User;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

// @internal
final readonly class DocumentToSearchResultItemTransformer implements DocumentToSearchResultItemTransformerInterface
{
    public function __construct(
        private DocumentLazyLoadingHandlerInterface $lazyLoadingHandler,
        private DocumentNormalizer $normalizer,
        private DocumentSearchResultDenormalizer $denormalizer,
        private PermissionServiceInterface $permissionService,
    ) {
    }

    public function transform(Document $document, ?User $user = null): DocumentSearchResultItem
    {
        try {
            $context = SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext();

            $searchResultItem = $this->denormalizer->denormalize(
                $this->normalizer->normalize($document, null, $context),
                DataObjectSearchResultItem::class,
                null,
                $context
            );

            $searchResultItem = $this->lazyLoadingHandler
                ->apply($searchResultItem, $user);

            return $searchResultItem
                ->setPermissions($this->permissionService->getDocumentPermissions($searchResultItem, $user));

        } catch (Exception|ExceptionInterface $e) {
            throw new SearchResultItemTransformationException(
                'Error transforming document to search result item',
                0,
                $e
            );
        }
    }
}
