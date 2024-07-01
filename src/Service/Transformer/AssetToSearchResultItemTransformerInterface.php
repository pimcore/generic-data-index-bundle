<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;
use Pimcore\Model\User;

interface AssetToSearchResultItemTransformerInterface
{
    public function transform(Asset $asset, ?User $user = null): AssetSearchResultItem;
}