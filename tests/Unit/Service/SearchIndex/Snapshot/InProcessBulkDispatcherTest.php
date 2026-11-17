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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\BulkChunk;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkSenderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\InProcessBulkDispatcher;

final class InProcessBulkDispatcherTest extends Unit
{
    private const SHORT_NAME = 'data-object_simple';

    private const BODY = "{\"index\":{\"_id\":\"7\"}}\n{\"a\":1}\n";

    /** @var list<string> */
    private array $files = [];

    protected function _after(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $this->files = [];
    }

    public function testEveryChunkIsSentAndItsFileRemoved(): void
    {
        $sent = [];
        $dispatcher = new InProcessBulkDispatcher($this->sender(function (string $body) use (&$sent): void {
            $sent[] = $body;
        }));
        $dispatcher->start($this->target());

        $first = $this->chunk();
        $second = $this->chunk();
        $dispatcher->dispatch($first);
        $dispatcher->dispatch($second);
        $dispatcher->finish();

        $this->assertSame([self::BODY, self::BODY], $sent);
        $this->assertFileDoesNotExist($first->getPath());
        $this->assertFileDoesNotExist($second->getPath());
    }

    public function testAFailedChunkSurfacesAndItsFileIsRemovedAnyway(): void
    {
        $dispatcher = new InProcessBulkDispatcher($this->sender(static function (): void {
            throw new SnapshotImportException('engine refused the bulk');
        }));
        $dispatcher->start($this->target());
        $chunk = $this->chunk();

        try {
            $dispatcher->dispatch($chunk);
            $this->fail('the failure of the bulk request must surface');
        } catch (SnapshotImportException $e) {
            $this->assertSame('engine refused the bulk', $e->getMessage());
        }

        $dispatcher->abort();
        $this->assertFileDoesNotExist($chunk->getPath());
    }

    public function testAMissingChunkFileIsReported(): void
    {
        $dispatcher = new InProcessBulkDispatcher($this->sender(static fn () => null));
        $dispatcher->start($this->target());

        $this->expectException(SnapshotImportException::class);
        $dispatcher->dispatch(new BulkChunk('/does/not/exist.ndjson', 1, 1));
    }

    public function testEachChunkIsLoggedLikeTheWorkerPoolLogsIt(): void
    {
        $handler = new TestHandler();
        $dispatcher = new InProcessBulkDispatcher(
            $this->sender(static fn () => null),
            new Logger('test', [$handler])
        );
        $dispatcher->start($this->target());
        $dispatcher->dispatch($this->chunk());

        $records = array_values(array_filter(
            $handler->getRecords(),
            static fn ($record) => $record->message === 'Snapshot import bulk'
        ));
        $this->assertCount(1, $records);
        $this->assertSame(self::SHORT_NAME, $records[0]->context['index']);
        $this->assertSame(1, $records[0]->context['documents']);
        $this->assertSame(strlen(self::BODY), $records[0]->context['bytes']);
    }

    private function sender(callable $send): BulkSenderInterface
    {
        return new class($send) implements BulkSenderInterface {
            public function __construct(private $send)
            {
            }

            public function send(string $ndjsonBody, string $indexShortName): void
            {
                ($this->send)($ndjsonBody, $indexShortName);
            }
        };
    }

    private function target(): IndexTarget
    {
        return new IndexTarget(self::SHORT_NAME, 'pimcore_' . self::SHORT_NAME, 'dataObject');
    }

    private function chunk(): BulkChunk
    {
        $path = tempnam(sys_get_temp_dir(), 'gdi-chunk-');
        file_put_contents($path, self::BODY);
        $this->files[] = $path;

        return new BulkChunk($path, 1, strlen(self::BODY));
    }
}
