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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\ElementWorkspacesQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class SearchHelper implements ElementSearchHelperInterface
{
    use SearchService\Traits\SearchHelperTrait;

    public function __construct(
        private SearchService\Asset\SearchHelper $assetSearchHelper,
        private SearchService\Document\SearchHelper $documentSearchHelper,
        private SearchService\DataObject\SearchHelper $dataObjectSearchHelper,
        private SearchIndexServiceInterface $searchIndexService,
        private SearchModifierServiceInterface $searchModifierService,
    ) {
    }

    public function addSearchRestrictions(SearchInterface $search): SearchInterface
    {
        $user = $search->getUser();
        if (!$user) {
            return $search;
        }

        if (!$user->isAdmin()) {
            $search->addModifier(new ElementWorkspacesQuery(
                $user,
                PermissionTypes::LIST->value
            ));
        }

        return $search;
    }

    private function hydrateSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user = null
    ): ElementSearchResultItemInterface {
        $elementType = SystemField::ELEMENT_TYPE->getData($searchResultHit->getSource());

        return match($elementType) {
            ElementType::DATA_OBJECT->value => $this->dataObjectSearchHelper->hydrateSearchResultHit(
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
