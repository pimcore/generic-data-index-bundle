<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\PqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

class PqlFilterTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before()
    {
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests
    public function testPqlFilter()
    {
        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject();
        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject();
        /** @var Unittest $object3 */
        $object3= TestHelper::createEmptyObject();
        /** @var Unittest $object4 */
        $object4= TestHelper::createEmptyObject();

        $object1
            ->setInput('test1')
            ->setNumber(10)
            ->save()
        ;

        $object2
            ->setInput('test2')
            ->setNumber(20)
            ->setMultihref([$object1])
            ->save()
        ;

        $object3
            ->setInput(null)
            ->save()
        ;

        $object4
            ->setInput('')
            ->save()
        ;

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $testCases = [
            'input = "test1"' => [$object1->getId()],
            'input like "test*"' => [$object1->getId(), $object2->getId()],
            'input like "t*1"' => [$object1->getId()],
            'input like "tes?1"' => [$object1->getId()],
            'input like "Tes?1"' => [$object1->getId()],
            'input like "test?1"' => [],
            'input like "notfound*"' => [],

            'input not like "test*"' => [$object3->getId(), $object4->getId()],

            'number > 15' => [$object2->getId()],
            'number >= 10' => [$object1->getId(), $object2->getId()],
            'number < 15' => [$object1->getId()],
            'number <= 20' => [$object1->getId(), $object2->getId()],
            'number < 20.1' => [$object1->getId(), $object2->getId()],
            'number = 10' => [$object1->getId()],

            'number = 10 and input = "test1"' => [$object1->getId()],
            'number > 10 and number < 21' => [$object2->getId()],
            'number = 10 and input = "test2"' => [],
            'number = 10 or input = "test2"' => [$object1->getId(), $object2->getId()],
            'number = 10 or input = "test3"' => [$object1->getId()],
            '(number = 10 and input = "test1")' => [$object1->getId()],
            '(number = 10 and input = "test1") or number = 20' => [$object1->getId(), $object2->getId()],
            'input = "foo" or ((number = 10 and input = "test1") or number = 20)' => [$object1->getId(), $object2->getId()],

            'Query("standard_fields.input:(test1 or test2)")' => [$object1->getId(), $object2->getId()],
            '(Query("standard_fields.input:(test1 or test2)") and number <=20)' => [$object1->getId(), $object2->getId()],
            '(Query("standard_fields.input:(test1 or test2)") and number <20)' => [$object1->getId()],
            'Query("standard_fields.input:test1")' => [$object1->getId()],
            'Query("standard_fields.input:foo")' => [],

            'multihref:Unittest.input = "test1"' => [$object2->getId()],
            'multihref:Unittest.input = "test2"' => [],
            '(multihref:Unittest.input = "test2" or input ="test1")' => [$object1->getId()],

            'input = null' => [$object3->getId()],
            'input = ""' => [$object4->getId()],
            'input != null' => [$object1->getId(), $object2->getId(), $object4->getId()],
            'input != ""' => [$object1->getId(), $object2->getId(), $object3->getId()],
        ];

        foreach ($testCases as $query => $expectedIds) {
            $dataObjectSearch = $searchProvider
                ->createDataObjectSearch()
                ->addModifier(new PqlFilter($query))
                ->setClassDefinition($object1->getClass())
            ;
            $searchResult = $searchService->search($dataObjectSearch);
            $this->assertCount(count($expectedIds), $searchResult->getItems(), $query);
            $this->assertIdArrayEquals($expectedIds, $searchResult->getIds(), $query);
        }
    }

    private function assertIdArrayEquals(array $ids1, array $ids2, string $query)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2, $query);
    }
}
