<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\Asset\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter\DateAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class DateAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
    {
        $adapter = $this->getAdapter();

        $mapping = $adapter->getIndexMapping();
        $this->assertSame([
            'type' => 'date',
        ], $mapping);
    }

    public function testNormalize(): void
    {
        $adapter = $this->getAdapter();

        $result = $adapter->normalize(null);
        $this->assertNull($result);

        $result = $adapter->normalize(strtotime('2000-01-01T12:00:00Z'));

        $this->assertSame('2000-01-01T12:00:00+00:00', $result);

    }

    private function getAdapter(): DateAdapter
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);

        return new DateAdapter(
            $searchIndexConfigServiceInterfaceMock,
        );
    }
}
