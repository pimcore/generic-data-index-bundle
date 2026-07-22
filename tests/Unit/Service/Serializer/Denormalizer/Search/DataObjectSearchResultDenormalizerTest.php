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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Serializer\Denormalizer\Search;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DataObjectTypeSerializationHandlerService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DataObjectSearchResultDenormalizer;
use Pimcore\Model\DataObject\AbstractObject;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final class DataObjectSearchResultDenormalizerTest extends Unit
{
    /**
     * Index documents created before the children sort fields were indexed do not
     * contain them at all; denormalization must fall back to the Pimcore defaults
     * instead of failing on the non-nullable setters.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/pull/398
     */
    public function testDenormalizeFallsBackToDefaultsWhenChildrenSortFieldsAreMissing(): void
    {
        $searchResultItem = $this->denormalize($this->createSystemFieldsData());

        $this->assertSame(
            AbstractObject::OBJECT_CHILDREN_SORT_BY_DEFAULT,
            $searchResultItem->getChildrenSortBy()
        );
        $this->assertSame(
            AbstractObject::OBJECT_CHILDREN_SORT_ORDER_DEFAULT,
            $searchResultItem->getChildrenSortOrder()
        );
    }

    public function testDenormalizeFallsBackToDefaultsWhenChildrenSortFieldsAreNull(): void
    {
        $systemFields = $this->createSystemFieldsData([
            SystemField::CHILDREN_SORT_BY->value => null,
            SystemField::CHILDREN_SORT_ORDER->value => null,
        ]);

        $searchResultItem = $this->denormalize($systemFields);

        $this->assertSame(
            AbstractObject::OBJECT_CHILDREN_SORT_BY_DEFAULT,
            $searchResultItem->getChildrenSortBy()
        );
        $this->assertSame(
            AbstractObject::OBJECT_CHILDREN_SORT_ORDER_DEFAULT,
            $searchResultItem->getChildrenSortOrder()
        );
    }

    public function testDenormalizeKeepsExplicitChildrenSortValues(): void
    {
        $systemFields = $this->createSystemFieldsData([
            SystemField::CHILDREN_SORT_BY->value => 'index',
            SystemField::CHILDREN_SORT_ORDER->value => 'DESC',
        ]);

        $searchResultItem = $this->denormalize($systemFields);

        $this->assertSame('index', $searchResultItem->getChildrenSortBy());
        $this->assertSame('DESC', $searchResultItem->getChildrenSortOrder());
    }

    private function denormalize(array $systemFields): DataObjectSearchResultItem
    {
        $denormalizer = new DataObjectSearchResultDenormalizer(
            new DataObjectTypeSerializationHandlerService(new ServiceLocator([]))
        );

        return $denormalizer->denormalize(
            [FieldCategory::SYSTEM_FIELDS->value => $systemFields],
            DataObjectSearchResultItem::class,
            null,
            SerializerContext::SKIP_LAZY_LOADED_FIELDS->createContext()
        );
    }

    private function createSystemFieldsData(array $overrides = []): array
    {
        return $overrides + [
            SystemField::ID->value => 42,
            SystemField::CLASS_ID->value => 'CAR',
            SystemField::CLASS_NAME->value => 'Car',
            SystemField::PARENT_ID->value => 1,
            SystemField::TYPE->value => 'object',
            SystemField::PUBLISHED->value => true,
            SystemField::KEY->value => 'test-object',
            SystemField::INDEX->value => 0,
            SystemField::PATH->value => '/test/',
            SystemField::FULL_PATH->value => '/test/test-object',
            SystemField::USER_OWNER->value => 1,
            SystemField::USER_MODIFICATION->value => 1,
            SystemField::CREATION_DATE->value => '2026-07-21T10:00:00+0000',
            SystemField::MODIFICATION_DATE->value => '2026-07-21T10:00:00+0000',
        ];
    }
}
