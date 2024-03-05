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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;

/**
 * @internal
 */
final class TreeSortHandlers
{
    #[AsSearchModifierHandler]
    public function handleFullPathSort(OrderByFullPath $fullPathSort, SearchModifierContextInterface $context): void
    {
        $context->getSearch()
            ->addSort(
                new FieldSort(SystemField::FULL_PATH->getPath('keyword'), $fullPathSort->getDirection()->value)
            );
    }
}
