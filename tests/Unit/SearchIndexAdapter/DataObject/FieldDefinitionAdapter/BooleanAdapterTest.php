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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\BooleanAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\BooleanSelect;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;

/**
 * @internal
 */
final class BooleanAdapterTest extends Unit
{
    public function testGetSearchIndexMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new BooleanAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $mapping = $adapter->getIndexMapping();
        $this->assertSame([
            'type' => 'boolean',
        ], $mapping);
    }

    /**
     * Classification store values are stored as longtext, so checkbox values arrive
     * as strings ('0'/'1') instead of booleans and must be cast for the boolean
     * index mapping.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/pull/434
     */
    public function testNormalizeCastsCheckboxValuesToBool(): void
    {
        $adapter = $this->createAdapter(new Checkbox());

        $this->assertTrue($adapter->normalize('1'));
        $this->assertFalse($adapter->normalize('0'));
        $this->assertTrue($adapter->normalize(1));
        $this->assertFalse($adapter->normalize(0));
        $this->assertTrue($adapter->normalize(true));
        $this->assertFalse($adapter->normalize(false));
        $this->assertNull($adapter->normalize(null));
    }

    /**
     * The adapter is also registered for booleanSelect, which is tri-state:
     * 1 = yes, -1 = no, null/0 = empty. A plain bool cast would turn "no" (-1)
     * into true, so the field definition's own resource mapping must be used.
     */
    public function testNormalizeMapsBooleanSelectTriState(): void
    {
        $adapter = $this->createAdapter(new BooleanSelect());

        $this->assertTrue($adapter->normalize('1'));
        $this->assertTrue($adapter->normalize(1));
        $this->assertFalse($adapter->normalize('-1'));
        $this->assertFalse($adapter->normalize(-1));
        $this->assertNull($adapter->normalize('0'));
        $this->assertNull($adapter->normalize(0));
        $this->assertNull($adapter->normalize(null));
    }

    /**
     * Outside classification stores the object getter returns the already hydrated
     * bool (getDataFromResource ran at load time) — those values must pass through
     * unchanged instead of being nulled by a second resource conversion.
     */
    public function testNormalizeKeepsHydratedBooleanSelectValues(): void
    {
        $adapter = $this->createAdapter(new BooleanSelect());

        $this->assertTrue($adapter->normalize(true));
        $this->assertFalse($adapter->normalize(false));
    }

    private function createAdapter(Data $fieldDefinition): BooleanAdapter
    {
        $adapter = new BooleanAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }
}
