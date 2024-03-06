<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Snippet;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class SnippetSerializationHandler extends AbstractSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Document $document): array
    {
        if(!$document instanceof Document\Snippet) {
            return [];
        }

        return $this->getPageSystemFields($document);
    }

    public function createSearchResultModel(array $indexData): DocumentSearchResultItem
    {
        $document = new Snippet();

        return $this->getPageResultModelFields($document, $indexData);
    }
}
