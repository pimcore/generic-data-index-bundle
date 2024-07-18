<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Model\DataObject;
use Pimcore\Model\User;

interface DataObjectToSearchResultItemTransformerInterface
{
    public function transform(DataObject $dataObject, ?User $user = null): DataObjectSearchResultItem;
}