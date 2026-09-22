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

use Exception;
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\RefreshIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\SearchClient\SearchClientInterface;
use Throwable;

/**
 * Streams a snapshot file into the bulk API. The snapshot lines are the documents' JSON as the
 * source engine returned them, so they go into the bulk body unchanged: no decode into PHP
 * arrays and no re-encode per document. Only the id is read from each line.
 *
 * For the duration of the replay the index runs in bulk-loading mode (no automatic refresh,
 * asynchronous translog); its previous settings are put back afterwards, also after a failure,
 * so a partial index never stays in that mode.
 *
 * @internal
 */
final class DocumentReplayer implements DocumentReplayerInterface
{
    use LoggerAwareTrait;

    /**
     * The exporter writes the source unchanged and GDI documents always start with the system
     * fields, id first; when a line deviates, the id is read the slow way (json_decode). At most
     * 18 digits, so the cast can never clamp an oversized value to PHP_INT_MAX: longer digit runs
     * take the slow path, where json_decode yields a float that the integer check rejects.
     */
    private const ID_FAST_PATH = '/^\{"system_fields":\{"id":(\d{1,18})[,}]/';

    private const MAX_REPORTED_ITEM_ERRORS = 3;

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly DocumentFileReader $documentFileReader,
        private readonly ReplayIndexSettingsInterface $replayIndexSettings,
        private readonly int $bulkSize,
        private readonly int $bulkBytes,
    ) {
    }

    public function replay(IndexTarget $target, ManifestIndex $entry, string $localFile): void
    {
        $backup = $this->replayIndexSettings->apply($target->aliasName);

        try {
            $this->stream($target, $entry, $localFile);
        } catch (Throwable $e) {
            try {
                $this->replayIndexSettings->restore($target->aliasName, $backup);
            } catch (SnapshotImportException $restoreError) {
                $this->logger?->warning('Could not restore index settings after a failed replay', [
                    'index' => $target->aliasName,
                    'error' => $restoreError->getMessage(),
                ]);
            }

            throw $e;
        }
        $this->replayIndexSettings->restore($target->aliasName, $backup);
    }

    /**
     * @throws SnapshotImportException
     */
    private function stream(IndexTarget $target, ManifestIndex $entry, string $localFile): void
    {
        try {
            $body = '';
            $pending = 0;
            $pendingBytes = 0;
            foreach ($this->documentFileReader->readRawLines($localFile) as $line) {
                $id = $this->extractId($line->json, $entry);
                // Flush what is pending if this line would push the request over the byte
                // budget, so a bulk body never exceeds it by a document. A single document larger
                // than the whole budget still goes out on its own (post-add check below).
                if ($pending > 0 && $pendingBytes + $line->bytes > $this->bulkBytes) {
                    $this->commit($target, $body, $pending, $pendingBytes);
                    $body = '';
                    $pending = 0;
                    $pendingBytes = 0;
                }
                $body .= '{"index":{"_index":"' . $target->aliasName . '","_id":' . $id . "}}\n" . $line->json . "\n";
                $pending++;
                $pendingBytes += $line->bytes;
                if ($pending >= $this->bulkSize || $pendingBytes >= $this->bulkBytes) {
                    $this->commit($target, $body, $pending, $pendingBytes);
                    $body = '';
                    $pending = 0;
                    $pendingBytes = 0;
                }
            }
            if ($pending > 0) {
                $this->commit($target, $body, $pending, $pendingBytes);
            }
        } catch (InvalidSnapshotException $e) {
            throw new SnapshotImportException(
                sprintf('Import of index "%s" failed: %s', $target->shortName, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * @throws SnapshotImportException
     */
    private function extractId(string $json, ManifestIndex $entry): int
    {
        if (preg_match(self::ID_FAST_PATH, $json, $m) === 1) {
            return (int) $m[1];
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SnapshotImportException(
                sprintf('Invalid JSON document in "%s": %s', $entry->file, $e->getMessage()),
                0,
                $e,
            );
        }
        $id = is_array($document) ? ($document['system_fields']['id'] ?? null) : null;
        if (!is_int($id)) {
            throw new SnapshotImportException(
                sprintf('Document without integer system_fields.id in "%s"', $entry->file),
            );
        }

        return $id;
    }

    /**
     * Never refresh per batch: the importer refreshes once, after every document has been
     * replayed, and the index runs with refresh disabled during the replay anyway.
     *
     * @throws SnapshotImportException
     */
    private function commit(IndexTarget $target, string $body, int $documents, int $bytes): void
    {
        $this->logger?->debug('Snapshot import bulk', [
            'index' => $target->shortName,
            'documents' => $documents,
            'bytes' => $bytes,
        ]);

        try {
            $response = $this->client->bulk(['body' => $body, 'refresh' => RefreshIndexMode::NOT_REFRESH->value]);
        } catch (Exception $e) {
            throw new SnapshotImportException(
                sprintf('Import of index "%s" failed: %s', $target->shortName, $e->getMessage()),
                0,
                $e,
            );
        }
        if ($response['errors'] ?? true) {
            throw new SnapshotImportException(sprintf(
                'Import of index "%s" failed: bulk request reported errors: %s',
                $target->shortName,
                $this->describeItemErrors($response),
            ));
        }
    }

    private function describeItemErrors(array $response): string
    {
        $errors = [];
        foreach ($response['items'] ?? [] as $item) {
            $action = is_array($item) ? reset($item) : null;
            if (!is_array($action) || !isset($action['error'])) {
                continue;
            }
            $error = $action['error'];
            $errors[] = sprintf(
                'id %s: %s',
                (string) ($action['_id'] ?? '?'),
                is_array($error) ? (($error['type'] ?? '') . ' ' . ($error['reason'] ?? '')) : (string) $error,
            );
            if (count($errors) >= self::MAX_REPORTED_ITEM_ERRORS) {
                break;
            }
        }

        return $errors === [] ? 'no item error details in the response' : implode('; ', $errors);
    }
}
