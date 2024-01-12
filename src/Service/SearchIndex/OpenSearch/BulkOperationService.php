<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

class BulkOperationService
{
    use LoggerAwareTrait;

    private array $bulkOperationData = [];

    public function __construct(private OpenSearchService $openSearchService)
    {
    }

    public function add(array $data): BulkOperationService
    {
        $this->bulkOperationData[] = $data;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function commit()
    {
        if(!count($this->bulkOperationData)) {
            return true;
        }

        $this->logger->info('Commit bulk to index.');

        $response = $this->openSearchService->getOpenSearchClient()->bulk([
            'body' => $this->bulkOperationData,
        ]);

        $this->bulkOperationData = [];

        if ($response['errors'] ?? true) {
            throw new \Exception('OpenSearch bulk produced errors: '. json_encode($response));
        }
    }
}
