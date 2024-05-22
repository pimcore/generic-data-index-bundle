<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

readonly class SearchModifierContext implements SearchModifierContextInterface
{
    public function __construct(
        private OpenSearchSearchInterface $search,
        private SearchInterface $originalSearch,
    ) {
    }

    public function getSearch(): OpenSearchSearchInterface
    {
        return $this->search;
    }

    public function getOriginalSearch(): SearchInterface
    {
        return $this->originalSearch;
    }
}
