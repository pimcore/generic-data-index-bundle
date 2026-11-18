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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Source;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

/**
 * Fetches only the system fields of the found data objects from the search index.
 *
 * The result items lazy-load their full search index data on the first call of getSearchIndexData().
 * Use it for searches that do not read the search index data of most result items.
 * It has no effect on asset, document and element searches.
 */
final readonly class SystemFieldsOnlySource implements SearchModifierInterface
{
}
