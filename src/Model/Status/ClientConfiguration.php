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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Status;

/**
 * The search client the bundle is configured to use. Public API: part of the read-only status API
 * (see IndexQueueStatusServiceInterface). It holds names only; hosts, credentials and paths of the client
 * connection are never part of it. The values are reported as configured, also when they are empty, so a
 * misconfiguration can be diagnosed instead of failing the status call.
 */
final readonly class ClientConfiguration
{
    /**
     * @param string $clientType value of index_service.client_params.client_type ("openSearch" or "elasticsearch")
     * @param string $clientName value of index_service.client_params.client_name
     * @param string $indexPrefix value of index_service.client_params.index_prefix
     */
    public function __construct(
        private string $clientType,
        private string $clientName,
        private string $indexPrefix,
    ) {
    }

    public function getClientType(): string
    {
        return $this->clientType;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getIndexPrefix(): string
    {
        return $this->indexPrefix;
    }
}
