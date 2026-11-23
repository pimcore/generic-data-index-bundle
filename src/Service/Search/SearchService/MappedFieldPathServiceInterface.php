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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\MappedFieldPathDeclaration;

/**
 * Answers a field path against the mappings of the indices a search reads.
 *
 * @internal
 */
interface MappedFieldPathServiceInterface
{
    /**
     * Which of the indices behind the search's alias declare this field path, and which do not.
     *
     * The answer is kept per index rather than reduced to a boolean, because an alias can be backed
     * by more than one concrete index and the path can be declared in only some of them.
     *
     * Only a definite answer is reported: a mapping that could not be read at all is
     * {@see MappedFieldPathDeclaration::unanswered()}, which asserts nothing about the path.
     *
     * $pqlFieldNameResolutionEnabled is the filter's own setting, passed on so the answer is about the
     * path the query will really read: with it on, PQL field name resolution may address a sub-field of
     * the path instead - a `.keyword` multi-field, `.sort`, `.id` - and a guard answered on the bare
     * path would not be a guard on the query.
     */
    public function resolveDeclaration(
        SearchInterface $search,
        string $fieldPath,
        bool $pqlFieldNameResolutionEnabled = false,
    ): MappedFieldPathDeclaration;
}
