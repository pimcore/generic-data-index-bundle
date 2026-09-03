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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\NumericAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric;

final class NumericAdapterTest extends Unit
{
    public function testIntegerFieldIsMappedAsLong(): void
    {
        $numeric = new Numeric();
        $numeric->setInteger(true);

        $this->assertSame(
            ['type' => AttributeType::LONG->value],
            $this->createAdapter($numeric)->getIndexMapping()
        );
    }

    public function testDecimalFieldIsMappedAsDouble(): void
    {
        $numeric = new Numeric();
        $numeric->setInteger(false);

        $this->assertSame(
            ['type' => AttributeType::DOUBLE->value],
            $this->createAdapter($numeric)->getIndexMapping()
        );
    }

    public function testFieldWithDecimalPrecisionIsMappedAsDouble(): void
    {
        $numeric = new Numeric();
        $numeric->setInteger(false);
        $numeric->setDecimalSize(20);
        $numeric->setDecimalPrecision(4);

        $this->assertSame(
            ['type' => AttributeType::DOUBLE->value],
            $this->createAdapter($numeric)->getIndexMapping()
        );
    }

    private function createAdapter(Numeric $fieldDefinition): NumericAdapter
    {
        $adapter = new NumericAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }
}
