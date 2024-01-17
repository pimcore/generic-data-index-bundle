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
    public function __construct(
        private readonly array $hosts,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly bool $sslVerification)
    {

    }

    public function createOpenSearchClient(): Client
    {
        $clientBuilder = (new ClientBuilder())
            ->setHosts($this->hosts);

        if($this->username && $this->password) {
            $clientBuilder
                ->setBasicAuthentication($this->username, $this->password);
        }

        $clientBuilder->setSSLVerification($this->sslVerification);

        return $clientBuilder
            ->build();
    }
}
