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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\NumericRangeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\NumericRange;

final class NumericRangeAdapterTest extends Unit
{
    public function testIntegerRangeIsMappedAsLong(): void
    {
        $numericRange = new NumericRange();
        $numericRange->setInteger(true);

        $this->assertSame([
            'properties' => [
                'maximum' => [
                    'type' => AttributeType::LONG->value,
                ],
                'minimum' => [
                    'type' => AttributeType::LONG->value,
                ],
            ],
        ], $this->createAdapter($numericRange)->getIndexMapping());
    }

    public function testDecimalRangeIsMappedAsDouble(): void
    {
        $numericRange = new NumericRange();
        $numericRange->setInteger(false);

        $this->assertSame([
            'properties' => [
                'maximum' => [
                    'type' => AttributeType::DOUBLE->value,
                ],
                'minimum' => [
                    'type' => AttributeType::DOUBLE->value,
                ],
            ],
        ], $this->createAdapter($numericRange)->getIndexMapping());
    }

    private function createAdapter(NumericRange $fieldDefinition): NumericRangeAdapter
    {
        $adapter = new NumericRangeAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }
}
