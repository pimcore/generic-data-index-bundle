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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\BulkOperationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class BulkOperationServiceTest extends Unit
{
    private const GENERIC_MESSAGE = 'Bulk operation produced errors: {"errors":true';

    /**
     * Regression test for pimcore/platform-version#647: the queued deletes of a deleted class target an index
     * that is already gone. There is nothing left to delete, so the request is done, not failed.
     */
    public function testDeletionsFromAMissingIndexAreDone(): void
    {
        $service = $this->createService([
            'errors' => true,
            'items' => [
                $this->missingIndexItem('delete', 1),
                ['delete' => ['_index' => 'pimcore_asset', '_id' => 2, 'status' => 200, 'result' => 'deleted']],
                ['index' => ['_index' => 'pimcore_asset', '_id' => 3, 'status' => 201]],
            ],
        ]);
        $service->addDeletion('pimcore_class_deleted', 1);
        $service->addDeletion('pimcore_asset', 2);
        $service->add('pimcore_asset', 3, ['id' => 3]);

        $service->commit();

        $this->assertTrue(true, 'A deletion from a missing index does not fail the bulk request');
    }

    public function testAMissingIndexStillFailsAnyOtherOperation(): void
    {
        $message = $this->commitAndGetExceptionMessage([
            'errors' => true,
            'items' => [
                $this->missingIndexItem('delete', 1),
                $this->missingIndexItem('update', 2),
            ],
        ]);

        $this->assertStringStartsWith(self::GENERIC_MESSAGE, $message);
    }

    public function testADeletionWithAnotherErrorStillFails(): void
    {
        $message = $this->commitAndGetExceptionMessage([
            'errors' => true,
            'items' => [
                $this->missingIndexItem('delete', 1),
                ['delete' => [
                    '_index' => 'pimcore_asset',
                    '_id' => 2,
                    'status' => 409,
                    'error' => ['type' => 'version_conflict_engine_exception', 'reason' => 'version conflict'],
                ]],
            ],
        ]);

        $this->assertStringStartsWith(self::GENERIC_MESSAGE, $message);
    }

    public function testOtherBulkErrorsStillFail(): void
    {
        $message = $this->commitAndGetExceptionMessage([
            'errors' => true,
            'items' => [
                ['index' => [
                    '_index' => 'pimcore_data_object_car',
                    '_id' => 2,
                    'status' => 400,
                    'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'failed to parse field [price]'],
                ]],
            ],
        ]);

        $this->assertStringStartsWith(self::GENERIC_MESSAGE, $message);
        $this->assertStringContainsString('"_id":2', $message, 'the raw response stays available for debugging');
    }

    public function testAnErrorFlagWithoutItemErrorsStillFails(): void
    {
        $message = $this->commitAndGetExceptionMessage(['errors' => true, 'items' => []]);

        $this->assertStringStartsWith(self::GENERIC_MESSAGE, $message);
    }

    private function commitAndGetExceptionMessage(array $response): string
    {
        $service = $this->createService($response);
        $service->add('pimcore_data_object_car', 2, ['id' => 2]);

        try {
            $service->commit();
        } catch (BulkOperationException $exception) {
            return $exception->getMessage();
        }

        $this->fail('Expected a ' . BulkOperationException::class);
    }

    private function missingIndexItem(string $action, int $id): array
    {
        return [$action => [
            '_index' => 'pimcore_class_deleted',
            '_id' => $id,
            'status' => 404,
            'error' => ['type' => 'index_not_found_exception', 'reason' => 'no such index [pimcore_class_deleted]'],
        ]];
    }

    private function createService(array $response): BulkOperationService
    {
        $service = new BulkOperationService(
            $this->makeEmpty(SearchClientInterface::class, ['bulk' => $response]),
            $this->makeEmpty(SynchronousProcessingServiceInterface::class)
        );
        $service->setLogger(new NullLogger());

        return $service;
    }
}
