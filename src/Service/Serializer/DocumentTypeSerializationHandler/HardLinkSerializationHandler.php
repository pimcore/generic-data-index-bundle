<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document\HardLinkSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\HardLink;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class HardLinkSerializationHandler extends AbstractSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Document $document): array
    {
        if (!$document instanceof Document\Hardlink) {
            return [];
        }

        return [
            HardLinkSystemField::SOURCE_ID->value => $document->getSourceId(),
            HardLinkSystemField::CHILDREN_FROM_SOURCE->value => $document->getChildrenFromSource(),
            HardLinkSystemField::PROPERTIES_FROM_SOURCE->value => $document->getPropertiesFromSource(),
        ];
    }

    public function createSearchResultModel(array $indexData): DocumentSearchResultItem
    {
        $document = new HardLink();

        return $document
            ->setSourceId(HardLinkSystemField::SOURCE_ID->getData($indexData))
            ->setChildrenFromSource(HardLinkSystemField::CHILDREN_FROM_SOURCE->getData($indexData))
            ->setPropertiesFromSource(HardLinkSystemField::PROPERTIES_FROM_SOURCE->getData($indexData));
    }
}
