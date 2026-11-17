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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use Throwable;

/**
 * The one actionable explanation of an exceeded total fields limit, for every path that can hit it: the bulk
 * request, the index creation, and the field count this bundle refuses a mapping on before asking the engine.
 *
 * @internal
 */
interface TotalFieldsLimitMessageServiceInterface
{
    /**
     * The message for a bulk response whose items failed on the limit, naming each index with its own limit, or
     * an empty string when no item failed on it.
     *
     * @param array<string, mixed> $response
     */
    public function forBulkResponse(array $response): string;

    /**
     * The message for an engine refusal of a mapping request, or an empty string when the failure is another one.
     */
    public function forThrowable(Throwable $throwable, string $indexName): string;

    /**
     * The message for a mapping this bundle counted over the limit itself, before the engine was asked.
     *
     * @param array{path: string, fields: int}|null $largestContributor
     */
    public function forFieldCount(
        string $indexName,
        int $countedFields,
        int $limit,
        ?array $largestContributor
    ): string;
}
