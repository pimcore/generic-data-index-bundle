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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;

/**
 * Runs _update_by_query requests. Search clusters that refuse the API (hosted plans such as Bonsai below
 * "Business" answer it with HTTP 403) get it emulated with search and bulk requests instead.
 *
 * @internal
 */
interface UpdateByQueryServiceInterface
{
    /**
     * Sends $params to the native _update_by_query API, unless the emulation is configured
     * (search_settings.emulate_update_by_query) or the cluster refused the API earlier in this process.
     * A refusal with HTTP 403 switches to the emulation for the rest of the process.
     *
     * The emulation does not run the painless script of $params. It pages through the documents matching
     * $params['body']['query'] and writes back what $transform returns for each document's _source, so
     * $transform has to make the same change as the script. Documents it returns unchanged are not written.
     * Like the native API it uses optimistic concurrency control: a document changed between reading and
     * writing it is a version conflict, skipped with $params['conflicts'] = 'proceed' and a failure
     * otherwise.
     *
     * @param array $params the parameters SearchClientInterface::updateByQuery() takes
     * @param callable(array): array $transform
     *
     * @throws BulkOperationException if the emulation fails to write documents
     */
    public function updateByQuery(array $params, callable $transform): void;

    /**
     * true: emulate every following request of this process, whatever the configuration and the cluster.
     * false: back to the configured / detected behaviour. Lets tests run both code paths on one cluster.
     */
    public function forceEmulation(bool $force): void;
}
