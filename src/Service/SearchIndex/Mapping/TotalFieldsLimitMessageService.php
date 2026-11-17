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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use JsonException;
use Throwable;

/**
 * @internal
 */
final class TotalFieldsLimitMessageService implements TotalFieldsLimitMessageServiceInterface
{
    private const EXCEEDED_PATTERN = '/Limit of total fields \[(\d+)\] has been exceeded/';

    private const EXCEEDED_PREFIX = 'Limit of total fields exceeded for index ';

    public function forBulkResponse(array $response): string
    {
        // a batch can span several indices, each with its own limit
        $limits = [];
        foreach ($response['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ($item as $result) {
                $limit = is_array($result) ? $this->matchLimit($result['error'] ?? null) : null;
                if ($limit !== null) {
                    $limits[$result['_index'] ?? 'unknown'] = $limit;
                }
            }
        }

        if ($limits === []) {
            return '';
        }

        $exceeded = [];
        foreach ($limits as $indexName => $limit) {
            $exceeded[] = sprintf('%s [%s]', $indexName, $limit);
        }

        return self::EXCEEDED_PREFIX . implode(', ', $exceeded)
            . ': the mapping has too many fields. ' . $this->getRemedy();
    }

    public function forThrowable(Throwable $throwable, string $indexName): string
    {
        $limit = $this->matchLimit($throwable->getMessage());
        if ($limit === null) {
            return '';
        }

        return sprintf(
            '%s%s [%s]: the mapping has too many fields. %s',
            self::EXCEEDED_PREFIX,
            $indexName,
            $limit,
            $this->getRemedy()
        );
    }

    public function forFieldCount(
        string $indexName,
        int $countedFields,
        int $limit,
        ?array $largestContributor
    ): string {
        $message = sprintf(
            'The mapping of index "%s" declares %d fields, over the configured limit of %d. ',
            $indexName,
            $countedFields,
            $limit
        );

        if ($largestContributor !== null) {
            $message .= sprintf(
                'Most of them come from "%s" with %d fields. ',
                $largestContributor['path'],
                $largestContributor['fields']
            );
        }

        return $message . $this->getRemedy();
    }

    /**
     * The limit the engine named, from an error structure or an error message, or null for any other failure.
     */
    private function matchLimit(mixed $error): ?string
    {
        if ($error === null) {
            return null;
        }

        try {
            $haystack = is_string($error) ? $error : json_encode($error, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return preg_match(self::EXCEEDED_PATTERN, $haystack, $matches) === 1 ? $matches[1] : null;
    }

    private function getRemedy(): string
    {
        return 'Large classification stores are mapped per group x key x language. Restrict the allowed groups '
            . 'of the classification store field in the class definition, or raise '
            . '"pimcore_generic_data_index.index_service.index_settings.index.mapping.total_fields.limit" '
            . '(nested keys) and recreate only the affected index, e.g. "bin/console '
            . 'generic-data-index:update:index --class-definition-id=<id> -r" for a data object class '
            . '(without --class-definition-id, -a or -d, -r deletes and recreates every index). ';
    }
}
