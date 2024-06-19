<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

/**
 * @internal
 */
interface TransformToAdapterSearchServiceInterface
{
    public function transform(SearchInterface $search, bool $enableOrderByPageNumber = false): AdapterSearchInterface;
}