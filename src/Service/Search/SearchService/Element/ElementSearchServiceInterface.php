<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ElementSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\SearchResult\ElementSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

interface ElementSearchServiceInterface
{
    /**
     * @throws ElementSearchException
     */
    public function search(SearchInterface $elementSearch): ElementSearchResult;
}