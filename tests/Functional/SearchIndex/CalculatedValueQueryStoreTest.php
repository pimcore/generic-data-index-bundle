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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedValueQueryStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\QueryStore;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * Reads calculated values from the real query tables written on save, so the lookup is checked
 * against the actual table schema (object_query_* is keyed by oo_id, object_localized_query_* by ooo_id).
 */
final class CalculatedValueQueryStoreTest extends Unit
{
    protected IndexTester $tester;

    private CalculatedValueQueryStoreServiceInterface $queryStoreService;

    protected function _before(): void
    {
        $this->tester->setupPimcoreClass_QueryStore();
        $this->queryStoreService = $this->tester->grabService(
            'generic-data-index.test.service.calculated-value-query-store'
        );
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testReadsTopLevelValueFromQueryTable(): void
    {
        $object = $this->createObject();

        $this->assertSame(
            'top-Name',
            $this->queryStoreService->getValue($object, $this->getTopLevelDefinition($object))
        );
    }

    public function testReadsLocalizedValueFromLocalizedQueryTablePerLanguage(): void
    {
        $object = $this->createObject();
        $definition = $this->getLocalizedDefinition($object);

        $this->assertSame('loc-English', $this->queryStoreService->getLocalizedValue($object, $definition, 'en'));
        $this->assertSame('loc-Deutsch', $this->queryStoreService->getLocalizedValue($object, $definition, 'de'));
    }

    private function createObject(): QueryStore
    {
        $object = new QueryStore();
        $object->setParentId(1);
        $object->setKey('query-store-' . uniqid());
        $object->setPublished(true);
        $object->setName('Name');
        $object->setLocName('English', 'en');
        $object->setLocName('Deutsch', 'de');
        $object->save();

        return $object;
    }

    private function getTopLevelDefinition(QueryStore $object): CalculatedValue
    {
        $definition = $object->getClass()->getFieldDefinition('calculatedTop');
        $this->assertInstanceOf(CalculatedValue::class, $definition);

        return $definition;
    }

    private function getLocalizedDefinition(QueryStore $object): CalculatedValue
    {
        $localizedFields = $object->getClass()->getFieldDefinition('localizedfields');
        $this->assertInstanceOf(Localizedfields::class, $localizedFields);

        $definition = $localizedFields->getFieldDefinition('calculatedLocalized');
        $this->assertInstanceOf(CalculatedValue::class, $definition);

        return $definition;
    }
}
