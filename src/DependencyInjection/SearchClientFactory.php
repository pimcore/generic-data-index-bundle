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

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ClientType;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 *
 * Resolves the correct search client at runtime so that client_type can be
 * supplied via an environment variable. Both possible client services are
 * wired in with NULL_ON_INVALID_REFERENCE so a missing bundle only fails
 * when the missing client type is actually requested.
 */
final class SearchClientFactory
{
    public function __construct(
        private readonly string $clientType,
        private readonly ?SearchClientInterface $openSearchClient,
        private readonly ?SearchClientInterface $elasticsearchClient,
    ) {
    }

    public function resolve(): SearchClientInterface
    {
        return match ($this->clientType) {
            ClientType::OPEN_SEARCH->value => $this->openSearchClient
                ?? throw new InvalidArgumentException(sprintf(
                    'No search client available for type "%s". Ensure the pimcore/opensearch-client bundle is installed and configured.',
                    $this->clientType
                )),
            ClientType::ELASTIC_SEARCH->value => $this->elasticsearchClient
                ?? throw new InvalidArgumentException(sprintf(
                    'No search client available for type "%s". Ensure the pimcore/elasticsearch-client bundle is installed and configured.',
                    $this->clientType
                )),
            default => throw new InvalidArgumentException(sprintf(
                'Invalid client_type "%s". Allowed values: %s.',
                $this->clientType,
                implode(', ', array_column(ClientType::cases(), 'value'))
            )),
        };
    }
}
