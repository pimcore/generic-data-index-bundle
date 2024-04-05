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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class OrderByPageNumber implements SearchModifierInterface
{
    public function __construct(
        private string $indexName,
        private SearchInterface $search,
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getSearch(): SearchInterface
    {
        return $this->search;
    }
}
