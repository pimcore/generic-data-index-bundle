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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\AbstractSearchHelper;
use Pimcore\Model\User;

/**
 * @internal
 */
final class SearchHelper extends AbstractSearchHelper
{
    public function __construct(
        private readonly SearchService\Asset\SearchHelper $assetSearchHelper,
        private readonly SearchService\Document\SearchHelper $documentSearchHelper,
        private readonly SearchService\DataObject\SearchHelper $dataObjecttSearchHelper,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService,
        private readonly UserPermissionServiceInterface $userPermissionService,
    ) {
        parent::__construct(
            $this->searchIndexService,
            $this->searchModifierService,
            $this->userPermissionService
        );
    }

    public function hydrateSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user = null
    ): ElementSearchResultItemInterface
    {
        $elementType = SystemField::ELEMENT_TYPE->getData($searchResultHit->getSource());

        return match($elementType) {
            ElementType::DATA_OBJECT->value => $this->dataObjecttSearchHelper->hydrateSearchResultHit(
                $searchResultHit,
                $childrenCounts,
                $user
            ),
            ElementType::DOCUMENT->value => $this->documentSearchHelper->hydrateSearchResultHit(
                $searchResultHit,
                $childrenCounts,
                $user
            ),
            ElementType::ASSET->value => $this->assetSearchHelper->hydrateSearchResultHit(
                $searchResultHit,
                $childrenCounts,
                $user
            ),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown element type "%s". Reindex of the search indices needed?',
                $elementType
            )),
        };

    }
}
