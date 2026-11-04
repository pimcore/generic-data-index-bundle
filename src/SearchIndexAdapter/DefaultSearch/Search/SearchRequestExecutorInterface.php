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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search;

/**
 * @internal
 */
interface SearchRequestExecutorInterface
{
    /**
     * Sends a search or count request to the search client.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $request
     * @param array<string, mixed> $params search client parameters with the query in the `body` key
     *
     * @return array<string, mixed>
     */
    public function execute(callable $request, array $params): array;
}
