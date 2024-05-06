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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\FullTextSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\WildcardFilterMode;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\WildcardFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\ElementKeySearch;

/**
 * @internal
 */
final class FullTextSearchHandlers
{
    #[AsSearchModifierHandler]
    public function handleElementKeySearch(
        ElementKeySearch $elementKeySearch,
        SearchModifierContextInterface $context
    ): void {
        if (empty($elementKeySearch->getSearchTerm())) {
            return;
        }

        $context->getSearch()
            ->addQuery(
                new WildcardFilter(
                    SystemField::KEY->getPath(),
                    $elementKeySearch->getSearchTerm(),
                    WildcardFilterMode::PREFIX
                )
            );
    }
}
