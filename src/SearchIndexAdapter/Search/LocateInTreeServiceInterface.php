<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

interface LocateInTreeServiceInterface
{
    public function getPageNumber(SearchInterface $search, int $elementId): ?int;
}