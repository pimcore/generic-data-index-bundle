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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\MessageHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\IndexUpdateQueueHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolver;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use ReflectionClass;
use RuntimeException;

final class IndexUpdateQueueHandlerTest extends Unit
{
    /**
     * A message carrying a mode override must apply it for the batch and clear it afterwards,
     * so a later message on the same worker falls back to the configured mode.
     */
    public function testMessageModeOverrideIsAppliedForTheBatchAndCleared(): void
    {
        $resolver = new CalculatedFieldsIndexModeResolver('query_store');
        $observedDuringProcessing = null;

        $indexQueueService = $this->makeEmpty(IndexQueueServiceInterface::class, [
            'handleIndexQueueEntries' => function () use ($resolver, &$observedDuringProcessing): void {
                $observedDuringProcessing = $resolver->getMode();
            },
        ]);

        $handler = new IndexUpdateQueueHandler($indexQueueService, $this->repository(), $resolver);

        // empty entries: the (final) repository is never invoked, so it need not be doubled
        $handler(new IndexUpdateQueueMessage([], CalculatedFieldsIndexMode::LIVE));

        $this->assertSame(CalculatedFieldsIndexMode::LIVE, $observedDuringProcessing, 'override applies during the batch');
        $this->assertNull($resolver->getOverrideMode(), 'override is cleared after the batch');
        $this->assertSame(CalculatedFieldsIndexMode::QUERY_STORE, $resolver->getMode(), 'falls back to configured mode');
    }

    /**
     * A message with no mode override must not touch the resolver.
     */
    public function testNoMessageModeLeavesTheResolverUntouched(): void
    {
        $resolver = new CalculatedFieldsIndexModeResolver('query_store');

        $handler = new IndexUpdateQueueHandler(
            $this->makeEmpty(IndexQueueServiceInterface::class, ['handleIndexQueueEntries' => Expected::once()]),
            $this->repository(),
            $resolver,
        );

        $handler(new IndexUpdateQueueMessage([]));

        $this->assertNull($resolver->getOverrideMode());
    }

    /**
     * The override must be cleared even when processing throws, so a failing batch does not
     * leak its mode onto subsequent messages.
     */
    public function testOverrideIsClearedWhenProcessingThrows(): void
    {
        $resolver = new CalculatedFieldsIndexModeResolver('query_store');

        $indexQueueService = $this->makeEmpty(IndexQueueServiceInterface::class, [
            'handleIndexQueueEntries' => function (): void {
                throw new RuntimeException('boom');
            },
        ]);

        $handler = new IndexUpdateQueueHandler($indexQueueService, $this->repository(), $resolver);

        try {
            $handler(new IndexUpdateQueueMessage([], CalculatedFieldsIndexMode::LIVE));
            $this->fail('expected the processing exception to propagate');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull($resolver->getOverrideMode(), 'override cleared even on failure');
    }

    /**
     * IndexQueueRepository is final and cannot be doubled; the tests pass empty entries so it is
     * never invoked, so a bare instance without constructor is sufficient.
     */
    private function repository(): IndexQueueRepository
    {
        return (new ReflectionClass(IndexQueueRepository::class))->newInstanceWithoutConstructor();
    }
}
