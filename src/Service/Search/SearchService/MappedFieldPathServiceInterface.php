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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

/**
 * Answers a field path against the mapping of the index a search runs against.
 *
 * @internal
 */
interface MappedFieldPathServiceInterface
{
    /**
     * Whether the mapping positively rules this field path out: it was readable and does not declare
     * the path, so no document of the index can hold a value there.
     *
     * Only a definite answer is reported. A path the mapping declares, and a mapping that could not
     * be read at all, both return false - the caller then builds the query it would have built
     * anyway, rather than suppressing a filter on a guess.
     */
    public function isUndeclaredInMapping(SearchInterface $search, string $fieldPath): bool;
}
