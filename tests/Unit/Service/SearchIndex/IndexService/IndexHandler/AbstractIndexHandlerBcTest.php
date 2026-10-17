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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService\IndexHandler;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexOutcome;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractIndexHandler is not internal: subclasses written against the released 2.5 API keep working.
 *
 * @internal
 */
final class AbstractIndexHandlerBcTest extends Unit
{
    public function testSubclassCallingTheParentConstructorWithFourArgumentsStillCopies(): void
    {
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getMapping' => ['test_alias-odd' => ['mappings' => ['properties' => [
                'standard_fields' => ['properties' => ['price' => ['type' => 'text']]],
            ]]]],
            'reindexWithoutFields' => new ReindexOutcome(ReindexResult::SUCCESS),
        ]);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $handler = new class($searchIndexService, $config, $events, $mapping) extends AbstractIndexHandler {
            public function __construct(
                SearchIndexServiceInterface $searchIndexService,
                SearchIndexConfigServiceInterface $searchIndexConfigService,
                EventDispatcherInterface $eventDispatcher,
                IndexMappingServiceInterface $indexMappingService,
            ) {
                @parent::__construct(
                    $searchIndexService,
                    $searchIndexConfigService,
                    $eventDispatcher,
                    $indexMappingService
                );
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['standard_fields' => ['properties' => ['price' => ['type' => 'float']]]];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        $result = $handler->reindexMappingWithResult();

        $this->assertSame(['standard_fields.price'], $result->getStrippedPaths());
        $this->assertFalse($result->isRecreated());
    }

    public function testSubclassOverridingReindexMappingWithTheReleasedSignatureLoads(): void
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $handler = new class($search, $config, $events, $mapping) extends AbstractIndexHandler {
            public int $calls = 0;

            public function reindexMapping(
                ?ClassDefinition $context = null,
                ?array $mappingProperties = null
            ): void {
                $this->calls++;
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };

        $handler->reindexMapping();

        $this->assertSame(1, $handler->calls, 'The released void signature can still be overridden');
    }
}
