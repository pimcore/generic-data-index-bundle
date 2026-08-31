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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor;

/**
 * Transforms the fully serialized search body; runs for every search and count request.
 * Implementations must be side-effect-free.
 */
interface SearchBodyProcessorInterface
{
    /**
     * @param array<string, mixed> $body the serialized search body produced by toArray()
     *
     * @return array<string, mixed> the transformed (or unchanged) body
     */
    public function process(array $body, string $indexName): array;
}
