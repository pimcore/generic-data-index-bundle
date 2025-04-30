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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\DatetimeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Datetime;

/**
 * @internal
 */
final class DatetimeAdapterTest extends Unit
{
    public function testGetSearchIndexMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new DatetimeAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $fieldDefinition = new Datetime();
        $fieldDefinition->setRespectTimezone(true);
        $adapter->setFieldDefinition($fieldDefinition);

        $mapping = $adapter->getIndexMapping();
        $this->assertSame([
            'type' => 'date',
            'format' => 'strict_date_time_no_millis',
        ], $mapping);

        $fieldDefinition->setRespectTimezone(false);
        $mapping = $adapter->getIndexMapping();

        $this->assertSame([
            'type' => 'date',
            'format' => 'strict_date_hour_minute_second',
        ], $mapping);
    }
}
