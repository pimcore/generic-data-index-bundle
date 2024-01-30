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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use RuntimeException;

/**
 * @internal
 */
final class BulkOperationService implements BulkOperationServiceInterface
{
    use LoggerAwareTrait;

    private array $bulkOperationData = [];

    public function __construct(private readonly OpenSearchServiceInterface $openSearchService)
    {
    }

    public function add(array $data): BulkOperationService
    {
        $this->bulkOperationData[] = $data;

        return $this;
    }

    /**
     * @throws BulkOperationException
     */
    public function commit(): void
    {
        if(!count($this->bulkOperationData)) {
            return;
        }

        try {
            $this->logger->info('Commit bulk to index.');

            $response = $this->openSearchService->getOpenSearchClient()->bulk([
                'body' => $this->bulkOperationData,
            ]);

            $this->bulkOperationData = [];

            if ($response['errors'] ?? true) {
                $responseEncoded = json_encode($response, JSON_THROW_ON_ERROR);

                throw new RuntimeException(
                    'OpenSearch bulk produced errors: '. $responseEncoded
                );
            }
        } catch (Exception $e) {
            throw new BulkOperationException($e->getMessage());
        }

    }
}
