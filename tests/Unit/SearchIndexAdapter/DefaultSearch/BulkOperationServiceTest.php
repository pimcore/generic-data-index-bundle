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
    /**
     * Regression test for pimcore/platform-version#504: the raw bulk response did not tell what to do
     * about an exceeded total fields limit.
     */
    public function testExceededTotalFieldsLimitProducesAnActionableMessage(): void
    {
        $message = $this->commitAndGetExceptionMessage([
            'errors' => true,
            'items' => [
                ['index' => ['_index' => 'pimcore_data_object_car', '_id' => 1, 'status' => 201]],
                ['index' => [
                    '_index' => 'pimcore_data_object_car',
                    '_id' => 2,
                    'status' => 400,
                    'error' => [
                        'type' => 'illegal_argument_exception',
                        'reason' => 'Limit of total fields [100000] has been exceeded while adding new fields [3]',
                    ],
                ]],
            ],
        ]);

        $this->assertStringStartsWith('Index pimcore_data_object_car', $message);
        $this->assertStringContainsString('[100000]', $message);
        $this->assertStringContainsString('allowed groups', $message);
        $this->assertStringContainsString(
            'pimcore_generic_data_index.index_service.index_settings.index.mapping.total_fields.limit',
            $message
        );
        $this->assertStringContainsString('generic-data-index:update:index -r', $message);
        $this->assertStringContainsString('"_id":2', $message, 'the raw response stays available for debugging');
    }

    public function testOtherBulkErrorsKeepTheGenericMessage(): void
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

        $this->assertStringStartsWith('Bulk operation produced errors: {"errors":true', $message);
        $this->assertStringNotContainsString('total_fields', $message);
    }

    private function commitAndGetExceptionMessage(array $response): string
    {
        $service = new BulkOperationService(
            $this->makeEmpty(SearchClientInterface::class, ['bulk' => $response]),
            $this->makeEmpty(SynchronousProcessingServiceInterface::class)
        );
        $service->setLogger(new NullLogger());
        $service->add('pimcore_data_object_car', 2, ['id' => 2]);

        try {
            $service->commit();
        } catch (BulkOperationException $exception) {
            return $exception->getMessage();
        }

        $this->fail('Expected a ' . BulkOperationException::class);
    }
}
