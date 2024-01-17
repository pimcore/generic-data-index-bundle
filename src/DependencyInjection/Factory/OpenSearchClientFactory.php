<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Factory;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;

class OpenSearchClientFactory
{
    public function createOpenSearchClient(): Client
    {
        return (new ClientBuilder())
            ->setHosts(['https://opensearch:9200'])
            ->setBasicAuthentication('admin', 'admin')
            ->setSSLVerification(false)
            ->build();
    }
}
