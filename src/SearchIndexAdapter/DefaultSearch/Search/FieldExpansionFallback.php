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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search;

use Throwable;

/**
 * A simple query string without a field list is expanded over every mapped field of the index.
 * On indices with a very large number of fields the search engine rejects this with a
 * "field expansion matches too many fields" error. In this case the request is repeated once with
 * an explicit list of the analyzed text fields instead of failing.
 *
 * @internal
 */
final class FieldExpansionFallback
{
    public const FIELDS = [
        'system_fields.key',
        'system_fields.key.analyzed',
        'system_fields.key.analyzed_ngram',
        'system_fields.text.analyzed',
        'system_fields.text.analyzed_ngram',
        'standard_fields.*.analyzed',
        'standard_fields.*.analyzed_ngram',
        'custom_fields.*.analyzed',
        'custom_fields.*.analyzed_ngram',
    ];

    private const ERROR_MESSAGE = 'field expansion matches too many fields';

    /**
     * Searches across several indices report the error as a failed shard of an otherwise
     * successful response, so that case is covered as well.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $request
     * @param array<string, mixed> $params search client parameters with the query in the `body` key
     *
     * @return array<string, mixed>
     */
    public static function execute(callable $request, array $params): array
    {
        try {
            $response = $request($params);
        } catch (Throwable $exception) {
            if (!self::isFieldExpansionError($exception)) {
                throw $exception;
            }

            return self::repeatWithRestrictedFields($request, $params) ?? throw $exception;
        }

        if (self::hasFieldExpansionShardFailure($response)) {
            return self::repeatWithRestrictedFields($request, $params) ?? $response;
        }

        return $response;
    }

    public static function isFieldExpansionError(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if (str_contains($current->getMessage(), self::ERROR_MESSAGE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $body
     * @param string[] $fields
     *
     * @return array<string, mixed>|null null if there is no simple query string without fields to restrict
     */
    public static function restrictFullTextFields(array $body, array $fields = self::FIELDS): ?array
    {
        $changed = false;
        $restricted = self::restrict($body, $fields, $changed);

        return $changed ? $restricted : null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function hasFieldExpansionShardFailure(array $response): bool
    {
        $failures = $response['_shards']['failures'] ?? [];
        if (!is_array($failures)) {
            return false;
        }

        foreach ($failures as $failure) {
            if (str_contains((string)json_encode($failure), self::ERROR_MESSAGE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $request
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|null null if there is nothing to restrict
     */
    private static function repeatWithRestrictedFields(callable $request, array $params): ?array
    {
        $restrictedBody = self::restrictFullTextFields($params['body'] ?? []);
        if ($restrictedBody === null) {
            return null;
        }

        $params['body'] = $restrictedBody;

        return $request($params);
    }

    /**
     * @param array<mixed> $node
     * @param string[] $fields
     *
     * @return array<mixed>
     */
    private static function restrict(array $node, array $fields, bool &$changed): array
    {
        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            // the required string `query` tells the clause apart from e.g. an aggregation of the same name
            if ($key === 'simple_query_string' && is_string($value['query'] ?? null) && empty($value['fields'])) {
                $value['fields'] = $fields;
                $changed = true;
            }

            $node[$key] = self::restrict($value, $fields, $changed);
        }

        return $node;
    }
}
