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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearch;
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
    ) {
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

        if ($search instanceof ElementSearch) {
            return IndexName::ELEMENT_SEARCH->value;
        }

        throw new InvalidArgumentException('Unsupported search type: ' . get_class($search));
    }
}
