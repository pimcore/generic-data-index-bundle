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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;

/**
 * @internal
 */
final class SearchInformation
{
    private const VERBOSITY_VERBOSE = 2;

    private const VERBOSITY_VERY_VERBOSE = 3;

    public function __construct(
        private readonly AdapterSearchInterface $search,
        private readonly bool $success,
        private readonly array $response,
        private readonly int|float $executionTime,
        private array $stackTrace
    ) {
    }

    public function getSearch(): AdapterSearchInterface
    {
        return $this->search;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function getExecutionTime(): int|float
    {
        return $this->executionTime;
    }

    public function getStackTrace(): array
    {
        return $this->stackTrace;
    }

    public function toArray(int $verbosity): array
    {
        $response = [
            'success' => $this->success,
            'execution_time_ms' => $this->executionTime,
            'search' => $this->search->toArray(),
        ];

        if ($verbosity >= self::VERBOSITY_VERBOSE) {
            $response['response'] = $this->response;
        }

        if ($verbosity >= self::VERBOSITY_VERY_VERBOSE) {
            $response['stackTrace'] = $this->stackTrace;
        }

        return $response;
    }
}
