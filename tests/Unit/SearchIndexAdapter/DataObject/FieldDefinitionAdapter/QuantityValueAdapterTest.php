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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\QuantityValueAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\QuantityValue;

final class QuantityValueAdapterTest extends Unit
{
    public function testIntegerValueIsMappedAsLong(): void
    {
        $quantityValue = new QuantityValue();
        $quantityValue->setInteger(true);

        $this->assertSame([
            'properties' => [
                'value' => [
                    'type' => AttributeType::LONG->value,
                ],
                'unitId' => [
                    'type' => AttributeType::TEXT->value,
                ],
            ],
        ], $this->createAdapter($quantityValue)->getIndexMapping());
    }

    public function testDecimalValueIsMappedAsDouble(): void
    {
        $quantityValue = new QuantityValue();
        $quantityValue->setInteger(false);

        $this->assertSame([
            'properties' => [
                'value' => [
                    'type' => AttributeType::DOUBLE->value,
                ],
                'unitId' => [
                    'type' => AttributeType::TEXT->value,
                ],
            ],
        ], $this->createAdapter($quantityValue)->getIndexMapping());
    }

    private function createAdapter(QuantityValue $fieldDefinition): QuantityValueAdapter
    {
        $adapter = new QuantityValueAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }
}
