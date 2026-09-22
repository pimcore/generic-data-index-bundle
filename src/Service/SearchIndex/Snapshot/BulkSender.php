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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Closure;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\RefreshIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class BulkSender implements BulkSenderInterface
{
    private const MAX_ATTEMPTS = 5;

    private const FIRST_BACKOFF_MILLISECONDS = 500;

    private const MAX_REPORTED_ITEM_ERRORS = 3;

    private const REJECTED = '/\b429\b|rejected_execution_exception|too_many_requests/i';

    private readonly Closure $sleep;

    /**
     * @param Closure(int $milliseconds): void|null $sleep injectable for tests; usleep by default
     */
    public function __construct(
        private readonly SearchClientInterface $client,
        ?Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    public function send(string $ndjsonBody, string $indexShortName): void
    {
        $backoff = self::FIRST_BACKOFF_MILLISECONDS;
        for ($attempt = 1; ; $attempt++) {
            $rejection = $this->attempt($ndjsonBody, $indexShortName);
            if ($rejection === null) {
                return;
            }
            if ($attempt >= self::MAX_ATTEMPTS) {
                throw new SnapshotImportException(sprintf(
                    'Import of index "%s" failed: the search engine kept rejecting the bulk request (%d attempts): %s',
                    $indexShortName,
                    $attempt,
                    $rejection,
                ));
            }
            ($this->sleep)($backoff);
            $backoff *= 2;
        }
    }

    /**
     * @return string|null null on success, the rejection message when the request should be retried
     *
     * @throws SnapshotImportException on every other failure
     */
    private function attempt(string $ndjsonBody, string $indexShortName): ?string
    {
        try {
            $response = $this->client->bulk(['body' => $ndjsonBody, 'refresh' => RefreshIndexMode::NOT_REFRESH->value]);
        } catch (Exception $e) {
            if (preg_match(self::REJECTED, $e->getMessage()) === 1) {
                return $e->getMessage();
            }

            throw new SnapshotImportException(
                sprintf('Import of index "%s" failed: %s', $indexShortName, $e->getMessage()),
                0,
                $e,
            );
        }
        if (!($response['errors'] ?? true)) {
            return null;
        }
        $errors = $this->itemErrors($response);
        if ($errors !== [] && $this->allRejections($errors)) {
            return $this->describe($errors);
        }

        throw new SnapshotImportException(sprintf(
            'Import of index "%s" failed: bulk request reported errors: %s',
            $indexShortName,
            $errors === [] ? 'no item error details in the response' : $this->describe($errors),
        ));
    }

    /**
     * @return list<array{id: string, status: int, type: string, reason: string}>
     */
    private function itemErrors(array $response): array
    {
        $errors = [];
        foreach ($response['items'] ?? [] as $item) {
            $action = is_array($item) ? reset($item) : null;
            if (!is_array($action) || !isset($action['error'])) {
                continue;
            }
            $error = is_array($action['error']) ? $action['error'] : ['reason' => (string) $action['error']];
            $errors[] = [
                'id' => (string) ($action['_id'] ?? '?'),
                'status' => (int) ($action['status'] ?? 0),
                'type' => (string) ($error['type'] ?? ''),
                'reason' => (string) ($error['reason'] ?? ''),
            ];
        }

        return $errors;
    }

    /**
     * @param list<array{id: string, status: int, type: string, reason: string}> $errors
     */
    private function allRejections(array $errors): bool
    {
        foreach ($errors as $error) {
            if ($error['status'] !== 429 && preg_match(self::REJECTED, $error['type']) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{id: string, status: int, type: string, reason: string}> $errors
     */
    private function describe(array $errors): string
    {
        $parts = [];
        foreach (array_slice($errors, 0, self::MAX_REPORTED_ITEM_ERRORS) as $error) {
            $parts[] = sprintf('id %s: %s %s', $error['id'], $error['type'], $error['reason']);
        }
        if (count($errors) > self::MAX_REPORTED_ITEM_ERRORS) {
            $parts[] = sprintf('… %d more', count($errors) - self::MAX_REPORTED_ITEM_ERRORS);
        }

        return implode('; ', $parts);
    }
}
