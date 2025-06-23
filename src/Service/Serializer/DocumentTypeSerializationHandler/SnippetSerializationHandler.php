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
        if (!$document instanceof Document\Snippet) {
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
