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

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\IndexAliasService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class IndexAliasServiceTest extends Unit
{
    /**
     * Regression: with opensearch-php >= 2.4 behind the legacy ClientBuilder transport, the
     * `client.ignore => [404]` option made the boolean HEAD request report a missing alias as
     * present, so the next `getAlias` failed with a 404. The client turns a 404 into `false`
     * on its own in every supported version; the option must not be sent.
     */
    public function testExistsAliasSendsNoClientIgnoreOption(): void
    {
        $captured = null;
        $service = $this->createService($this->makeEmpty(SearchClientInterface::class, [
            'existsIndexAlias' => Expected::once(function (array $params) use (&$captured): bool {
                $captured = $params;

                return false;
            }),
        ]));

        $this->assertFalse($service->existsAlias('pimcore_data-object', 'pimcore_data-object-odd'));
        $this->assertSame(['name' => 'pimcore_data-object', 'index' => 'pimcore_data-object-odd'], $captured);
    }

    public function testExistsAliasReturnsTheClientAnswer(): void
    {
        $service = $this->createService($this->makeEmpty(SearchClientInterface::class, [
            'existsIndexAlias' => true,
        ]));

        $this->assertTrue($service->existsAlias('pimcore_data-object'));
    }

    private function createService(SearchClientInterface $client): IndexAliasService
    {
        return new IndexAliasService(
            $client,
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
        );
    }
}
