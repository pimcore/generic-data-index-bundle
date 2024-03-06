<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document\LinkSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Link;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class LinkSerializationHandler extends AbstractSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Document $document): array
    {
        if(!$document instanceof Document\Link) {
            return [];
        }

        return [
            LinkSystemField::INTERNAL->value => $document->getInternal(),
            LinkSystemField::INTERNAL_TYPE->value => $document->getInternalType(),
            LinkSystemField::DIRECT->value => $document->getDirect(),
            LinkSystemField::LINK_TYPE->value => $document->getLinktype(),
            LinkSystemField::HREF->value => $document->getHref()
        ];
    }

    public function createSearchResultModel(array $indexData): DocumentSearchResultItem
    {
        $document = new Link();
        return $document
            ->setInternal(LinkSystemField::INTERNAL->getData($indexData))
            ->setInternalType(LinkSystemField::INTERNAL_TYPE->getData($indexData))
            ->setDirect(LinkSystemField::DIRECT->getData($indexData))
            ->setLinktype(LinkSystemField::LINK_TYPE->getData($indexData))
            ->setHref(LinkSystemField::HREF->getData($indexData));
    }
}