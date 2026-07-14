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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class AbstractIndexHandlerTest extends Unit
{
    private const ALIAS_NAME = 'test_alias';

    /**
     * An interrupted reindex can leave the alias missing while indexing traffic
     * auto-creates a concrete index carrying the exact alias name. Attaching the
     * alias then fails with invalid_alias_name_exception, so the recovery in
     * updateMapping() must remove such an index before recreating.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/issues/412
     */
    public function testUpdateMappingDeletesIndexSquattingOnAliasName(): void
    {
        $deletedIndices = [];
        $handler = $this->createHandler(
            indexSquatsAliasName: true,
            deletedIndices: $deletedIndices
        );

        $handler->updateMapping();

        $this->assertContains(self::ALIAS_NAME . '-even', $deletedIndices);
        $this->assertContains(self::ALIAS_NAME . '-odd', $deletedIndices);
        $this->assertContains(
            self::ALIAS_NAME,
            $deletedIndices,
            'An index occupying the alias name must be deleted before the alias is attached'
        );
    }

    public function testUpdateMappingKeepsAliasNameUntouchedWhenNoIndexSquatsOnIt(): void
    {
        $deletedIndices = [];
        $handler = $this->createHandler(
            indexSquatsAliasName: false,
            deletedIndices: $deletedIndices
        );

        $handler->updateMapping();

        $this->assertNotContains(self::ALIAS_NAME, $deletedIndices);
    }

    private function createHandler(bool $indexSquatsAliasName, array &$deletedIndices): AbstractIndexHandler
    {
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => false,
            'existsIndex' => static fn (string $indexName): bool => $indexSquatsAliasName
                && $indexName === self::ALIAS_NAME,
            'deleteIndex' => static function ($indexName, bool $silent = false) use (&$deletedIndices): void {
                $deletedIndices[] = $indexName;
            },
            'getCurrentIndexVersion' => '',
            'putMapping' => [],
        ]);

        $handler = new class($searchIndexService, $this->makeEmpty(SearchIndexConfigServiceInterface::class), $this->makeEmpty(EventDispatcherInterface::class), $this->makeEmpty(IndexMappingServiceInterface::class), ) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        return $handler;
    }
}
