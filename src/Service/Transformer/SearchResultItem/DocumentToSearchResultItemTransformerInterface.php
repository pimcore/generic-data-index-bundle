<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\User;

interface DocumentToSearchResultItemTransformerInterface
{
    public function transform(Document $document, ?User $user = null): DocumentSearchResultItem;
}