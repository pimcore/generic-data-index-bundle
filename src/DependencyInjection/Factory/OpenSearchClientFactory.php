<?php
declare(strict_types=1);

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
