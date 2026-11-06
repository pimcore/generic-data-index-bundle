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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Messenger;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexTimeoutException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Messenger\MappingUpdateRetryPolicy;
use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @internal
 */
final class MappingUpdateRetryPolicyTest extends Unit
{
    public function testACopyThatRanOutOfItsBudgetIsNotRetried(): void
    {
        $timeout = new ReindexTimeoutException('Reindex task 1 did not complete within 3600 seconds');
        $failure = new ClassDefinitionIndexUpdateFailedException(message: $timeout->getMessage(), previous: $timeout);

        try {
            (new MappingUpdateRetryPolicy())->rethrow($failure);
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertSame($failure, $e->getPrevious(), 'The failed transport keeps the whole cause chain');
            $this->assertStringContainsString('did not complete within 3600 seconds', $e->getMessage());
            $this->assertStringContainsString('max_polls', $e->getMessage());

            return;
        }

        $this->fail('A copy that ran out of its budget must not be retried with the same budget');
    }

    public function testTheTimeoutIsFoundAnywhereInTheCauseChain(): void
    {
        $failure = new RuntimeException(
            'Max reindex attempts reached',
            0,
            new ClassDefinitionIndexUpdateFailedException(
                message: 'wrapped',
                previous: new ReindexTimeoutException('did not complete within 60 seconds')
            )
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        (new MappingUpdateRetryPolicy())->rethrow($failure);
    }

    public function testEveryOtherFailureIsRethrownAsItIsSoThatMessengerRetriesIt(): void
    {
        $failure = new ReindexFailedException('cURL error 7: connection refused');

        try {
            (new MappingUpdateRetryPolicy())->rethrow($failure);
        } catch (ReindexFailedException $e) {
            $this->assertSame($failure, $e);

            return;
        }

        $this->fail('A transient failure is rethrown unchanged');
    }
}
