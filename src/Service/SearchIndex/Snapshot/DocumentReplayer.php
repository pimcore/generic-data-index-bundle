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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\RefreshIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

/**
 * @internal
 */
final class DocumentReplayer implements DocumentReplayerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly BulkOperationServiceInterface $bulkOperationService,
        private readonly DocumentFileReader $documentFileReader,
        private readonly int $bulkSize,
        private readonly int $bulkBytes,
    ) {
    }

    public function replay(IndexTarget $target, ManifestIndex $entry, string $localFile): void
    {
        try {
            $pending = 0;
            $pendingBytes = 0;
            foreach ($this->documentFileReader->readLines($localFile) as $line) {
                $document = $line->document;
                $id = $document[FieldCategory::SYSTEM_FIELDS->value][SystemField::ID->value] ?? null;
                if (!is_int($id)) {
                    throw new SnapshotImportException(
                        sprintf('Document without integer system_fields.id in "%s"', $entry->file),
                    );
                }
                $this->bulkOperationService->add($target->aliasName, $id, $document);
                $pending++;
                $pendingBytes += $line->bytes;
                // Flush by whichever limit is hit first: large documents reach the byte budget
                // long before the document count, and one oversized bulk body would exhaust PHP
                // memory or the search engine's request size limit.
                if ($pending >= $this->bulkSize || $pendingBytes >= $this->bulkBytes) {
                    $this->commit($target, $pending, $pendingBytes);
                    $pending = 0;
                    $pendingBytes = 0;
                }
            }
            if ($pending > 0) {
                $this->commit($target, $pending, $pendingBytes);
            }
        } catch (InvalidSnapshotException|BulkOperationException $e) {
            throw new SnapshotImportException(
                sprintf('Import of index "%s" failed: %s', $target->shortName, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Never refresh per batch: the default mode (`wait_for` when synchronous processing is
     * disabled) would make every batch commit wait for a refresh, even though the importer
     * refreshes once, after every document has been replayed.
     *
     * @throws BulkOperationException
     */
    private function commit(IndexTarget $target, int $documents, int $bytes): void
    {
        $this->logger?->debug('Snapshot import bulk', [
            'index' => $target->shortName,
            'documents' => $documents,
            'bytes' => $bytes,
        ]);
        $this->bulkOperationService->commit(RefreshIndexMode::NOT_REFRESH->value);
    }
}
