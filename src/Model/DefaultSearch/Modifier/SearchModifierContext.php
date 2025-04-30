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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

readonly class SearchModifierContext implements SearchModifierContextInterface
{
    public function __construct(
        private DefaultSearchInterface $search,
        private SearchInterface $originalSearch,
    ) {
    }

    public function getSearch(): DefaultSearchInterface
    {
        return $this->search;
    }

    public function getOriginalSearch(): SearchInterface
    {
        return $this->originalSearch;
    }
}
