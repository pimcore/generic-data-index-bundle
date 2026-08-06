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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class AbstractIndexHandlerChecksumTest extends Unit
{
    public function testGetClassMappingCheckSumIsStableForAssociativeKeyOrder(): void
    {
        $indexHandler = $this->getIndexHandler();

        $mappingA = [
            'standard_fields' => [
                'properties' => [
                    'localizedfields' => [
                        'properties' => [
                            'en' => ['properties' => ['name' => ['type' => 'text']]],
                            'de' => ['properties' => ['name' => ['type' => 'text']]],
                        ],
                    ],
                ],
            ],
        ];

        $mappingB = [
            'standard_fields' => [
                'properties' => [
                    'localizedfields' => [
                        'properties' => [
                            'de' => ['properties' => ['name' => ['type' => 'text']]],
                            'en' => ['properties' => ['name' => ['type' => 'text']]],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            $indexHandler->getClassMappingCheckSum($mappingA),
            $indexHandler->getClassMappingCheckSum($mappingB)
        );
    }

    private function getIndexHandler(): AbstractIndexHandler
    {
        return new class($this->makeEmpty(SearchIndexServiceInterface::class), $this->makeEmpty(SearchIndexConfigServiceInterface::class), $this->makeEmpty(EventDispatcherInterface::class), $this->makeEmpty(IndexMappingServiceInterface::class), ) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test';
            }
        };
    }
}
