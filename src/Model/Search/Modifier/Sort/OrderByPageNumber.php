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
