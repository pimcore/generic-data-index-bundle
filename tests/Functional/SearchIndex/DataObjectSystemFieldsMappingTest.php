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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ClassIdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\PqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Tests\Support\Util\TestHelper;

class DataObjectSystemFieldsMappingTest extends Unit
{
    private const CLASS_NAME = 'simple';

    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
        $this->recreateEmptyClassIndex();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testClassAndClassNameAreMappedBeforeAnyDocumentIsIndexed(): void
    {
        $indexName = $this->tester->getIndexName(self::CLASS_NAME, true);
        $mapping = $this->tester->getIndexMapping($indexName);
        $systemFields = $mapping[$indexName]['mappings']['properties']['system_fields']['properties'];

        foreach (['classId', 'className'] as $field) {
            $this->assertArrayHasKey($field, $systemFields);
            $this->assertSame(
                'keyword',
                $systemFields[$field]['fields']['keyword']['type'] ?? null,
                sprintf('The "%s.keyword" sub field is used by filters of this and other bundles', $field)
            );
        }
    }

    /**
     * @dataProvider pqlFieldProvider
     */
    public function testPqlFilterOnClassFieldsDoesNotRequireIndexedDocuments(string $query): void
    {
        $classDefinition = ClassDefinition::getByName(self::CLASS_NAME);
        $query = str_replace(
            ['{classId}', '{className}'],
            [$classDefinition->getId(), $classDefinition->getName()],
            $query
        );

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $result = $searchService->search(
            $searchProvider
                ->createDataObjectSearch()
                ->addModifier(new PqlFilter($query))
                ->setClassDefinition($classDefinition)
        );

        $this->assertSame([], $result->getIds(), $query);
    }

    public function testPqlFilterOnClassFieldsMatchesIndexedDocuments(): void
    {
        $object = TestHelper::createEmptyObject('', true, true, 'Pimcore\Model\DataObject\Simple');
        $classDefinition = $object->getClass();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        foreach (
            [
                sprintf('classId = "%s"', $classDefinition->getId()),
                sprintf('className = "%s"', $classDefinition->getName()),
            ] as $query
        ) {
            $result = $searchService->search(
                $searchProvider
                    ->createDataObjectSearch()
                    ->addModifier(new PqlFilter($query))
                    ->setClassDefinition($classDefinition)
            );

            $this->assertSame([$object->getId()], $result->getIds(), $query);
        }
    }

    public function testClassIdsFilterMatchesIndexedDocumentsOfTheRecreatedIndex(): void
    {
        $object = TestHelper::createEmptyObject('', true, true, 'Pimcore\Model\DataObject\Simple');
        $classDefinition = $object->getClass();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        foreach ([[$classDefinition->getId(), false], [$classDefinition->getName(), true]] as [$value, $useClassName]) {
            $result = $searchService->search(
                $searchProvider
                    ->createDataObjectSearch()
                    ->addModifier(new ClassIdsFilter([$value], false, $useClassName))
                    ->setClassDefinition($classDefinition)
            );

            $this->assertSame([$object->getId()], $result->getIds(), (string) $value);
        }
    }

    public static function pqlFieldProvider(): array
    {
        return [
            'class id' => ['classId = "{classId}"'],
            'class name' => ['className = "{className}"'],
            'type and class id' => ['type = "object" AND classId = "{classId}"'],
        ];
    }

    /**
     * Other tests leave dynamically mapped fields behind in the shared class index (cleanupIndex() only
     * deletes documents), so the index is created from scratch to get the mapping the bundle itself declares.
     */
    private function recreateEmptyClassIndex(): void
    {
        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService
            ->setReCreateIndex(true)
            ->updateClassDefinition(ClassDefinition::getByName(self::CLASS_NAME));
        $this->tester->flushIndex();
    }
}
