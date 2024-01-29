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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch;

use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use RuntimeException;

class BulkOperationService
{
    use LoggerAwareTrait;

    private array $bulkOperationData = [];

    public function __construct(private readonly OpenSearchService $openSearchService)
    {
    }

    public function add(array $data): BulkOperationService
    {
        $this->bulkOperationData[] = $data;

        return $this;
    }

    /**
     * @throws RuntimeException|JsonException
     */
    public function commit(): void
    {
        if(!count($this->bulkOperationData)) {
            return;
        }

        $this->logger->info('Commit bulk to index.');

        $response = $this->openSearchService->getOpenSearchClient()->bulk([
            'body' => $this->bulkOperationData,
        ]);

        $this->bulkOperationData = [];

        if ($response['errors'] ?? true) {
            throw new RuntimeException('OpenSearch bulk produced errors: '. json_encode($response,  JSON_THROW_ON_ERROR));
        }
    }
}
