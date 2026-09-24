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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkSender;
use Pimcore\SearchClient\SearchClientInterface;
use RuntimeException;

final class BulkSenderTest extends Unit
{
    /** @var array[] bulk params per attempt */
    private array $attempts = [];

    /** @var int[] requested sleeps in milliseconds */
    private array $sleeps = [];

    /** @var list<array|RuntimeException> what the client answers per attempt, in order */
    private array $answers = [];

    protected function _before(): void
    {
        $this->attempts = [];
        $this->sleeps = [];
        $this->answers = [];
    }

    public function testSendsTheBodyWithoutRefresh(): void
    {
        $this->answers = [['errors' => false, 'items' => []]];

        $this->sender()->send("{\"index\":{}}\n{}\n", 'asset');

        $this->assertCount(1, $this->attempts);
        $this->assertSame("{\"index\":{}}\n{}\n", $this->attempts[0]['body']);
        $this->assertSame('false', $this->attempts[0]['refresh']);
        $this->assertSame([], $this->sleeps);
    }

    public function testRetriesWithBackoffWhenTheEngineRejectsTheRequest(): void
    {
        // a full write queue answers 429 / rejected_execution_exception; the whole body is
        // idempotent (index actions by _id), so it is simply sent again after a pause
        $this->answers = [
            new RuntimeException('Bulk operation failed: 429 {"error":{"type":"rejected_execution_exception"}}'),
            new RuntimeException('Bulk operation failed: 429 {"error":{"type":"rejected_execution_exception"}}'),
            ['errors' => false, 'items' => []],
        ];

        $this->sender()->send('body', 'asset');

        $this->assertCount(3, $this->attempts);
        $this->assertSame([500, 1000], $this->sleeps, 'exponential backoff between attempts');
    }

    public function testRetriesWhenOnlyItemsWereRejected(): void
    {
        $this->answers = [
            ['errors' => true, 'items' => [
                ['index' => ['_id' => '1', 'status' => 201]],
                ['index' => [
                    '_id' => '2',
                    'status' => 429,
                    'error' => ['type' => 'rejected_execution_exception', 'reason' => 'queue full'],
                ]],
            ]],
            ['errors' => false, 'items' => []],
        ];

        $this->sender()->send('body', 'asset');

        $this->assertCount(2, $this->attempts);
        $this->assertSame([500], $this->sleeps);
    }

    public function testGivesUpAfterTheMaximumNumberOfAttempts(): void
    {
        $this->answers = array_fill(0, 5, new RuntimeException('429 rejected_execution_exception'));

        try {
            $this->sender()->send('body', 'asset');
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('asset', $e->getMessage());
            $this->assertStringContainsString('5 attempts', $e->getMessage());
            $this->assertStringContainsString('rejected_execution_exception', $e->getMessage());
        }
        $this->assertCount(5, $this->attempts);
        $this->assertSame([500, 1000, 2000, 4000], $this->sleeps);
    }

    public function testOtherItemErrorsFailImmediatelyWithTypeAndReason(): void
    {
        $this->answers = [['errors' => true, 'items' => [
            ['index' => [
                '_id' => '7',
                'status' => 400,
                'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'failed to parse'],
            ]],
        ]]];

        try {
            $this->sender()->send('body', 'asset');
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('mapper_parsing_exception', $e->getMessage());
            $this->assertStringContainsString('failed to parse', $e->getMessage());
            $this->assertStringContainsString('id 7', $e->getMessage());
        }
        $this->assertCount(1, $this->attempts);
    }

    public function testOtherClientFailuresFailImmediately(): void
    {
        $this->answers = [new RuntimeException('connection refused')];

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('connection refused');

        $this->sender()->send('body', 'asset');
        $this->assertCount(1, $this->attempts);
    }

    private function sender(): BulkSender
    {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'bulk' => function (array $params): array {
                $this->attempts[] = $params;
                $answer = array_shift($this->answers);
                if ($answer instanceof RuntimeException) {
                    throw $answer;
                }

                return $answer;
            },
        ]);

        return new BulkSender($client, function (int $milliseconds): void {
            $this->sleeps[] = $milliseconds;
        });
    }
}
