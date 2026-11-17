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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject;

/**
 * Adapters of relation fields that accept
 * {@see \Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\RelationSummaries} instead of the loaded related
 * elements in normalize() and getInheritedData(), so the related elements do not need to be loaded for indexing.
 *
 * @internal
 */
interface RelationSummaryAdapterInterface extends AdapterInterface
{
}
