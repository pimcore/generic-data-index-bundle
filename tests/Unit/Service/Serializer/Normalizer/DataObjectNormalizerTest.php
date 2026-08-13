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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Serializer\Normalizer;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectNormalizerException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Dependency\DependencyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedValueQueryStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DataObjectNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Localizedfield;

/**
 * @internal
 */
final class DataObjectNormalizerTest extends Unit
{
    public function testGlobalStateIsRestoredAfterNormalization(): void
    {
        AbstractObject::setGetInheritedValues(true);
        AbstractObject::setHideUnpublished(true);
        Localizedfield::setGetFallbackValues(false);

        $dataObject = $this->createConcreteObjectMock();

        $normalizer = $this->createNormalizer();
        $normalizer->normalize($dataObject);

        $this->assertTrue(
            AbstractObject::doGetInheritedValues(),
            'InheritedValues should be restored after normalization'
        );
        $this->assertTrue(
            AbstractObject::getHideUnpublished(),
            'HideUnpublished should be restored after normalization'
        );
        $this->assertFalse(
            Localizedfield::doGetFallbackValues(),
            'FallbackValues should be restored after normalization'
        );
    }

    public function testGlobalStateIsRestoredOnException(): void
    {
        AbstractObject::setGetInheritedValues(true);
        AbstractObject::setHideUnpublished(true);
        Localizedfield::setGetFallbackValues(false);

        $classDefinition = $this->createClassDefinition([
            'broken_field' => new ClassDefinition\Data\Input(),
        ]);

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => function () {
                throw new Exception('Normalization failed');
            },
        ]);

        $dataObject = $this->createConcreteObjectMock($classDefinition);

        $normalizer = $this->createNormalizer(fieldDefinitionService: $fieldDefinitionService);

        try {
            $normalizer->normalize($dataObject);
            $this->fail('Expected DataObjectNormalizerException was not thrown');
        } catch (DataObjectNormalizerException) {
            // Expected
        }

        $this->assertTrue(
            AbstractObject::doGetInheritedValues(),
            'InheritedValues should be restored even after exception'
        );
        $this->assertTrue(
            AbstractObject::getHideUnpublished(),
            'HideUnpublished should be restored even after exception'
        );
        $this->assertFalse(
            Localizedfield::doGetFallbackValues(),
            'FallbackValues should be restored even after exception'
        );
    }

    public function testHideUnpublishedIsDisabledDuringNormalization(): void
    {
        AbstractObject::setHideUnpublished(true);

        $classDefinition = $this->createClassDefinition([
            'test_field' => new ClassDefinition\Data\Input(),
        ]);

        $hideUnpublishedDuringNormalization = null;

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => function () use (&$hideUnpublishedDuringNormalization) {
                $hideUnpublishedDuringNormalization = AbstractObject::getHideUnpublished();

                return 'normalized_value';
            },
        ]);

        $dataObject = $this->createConcreteObjectMock($classDefinition);

        $normalizer = $this->createNormalizer(fieldDefinitionService: $fieldDefinitionService);
        $normalizer->normalize($dataObject);

        $this->assertFalse(
            $hideUnpublishedDuringNormalization,
            'HideUnpublished should be false during field normalization'
        );
        $this->assertTrue(
            AbstractObject::getHideUnpublished(),
            'HideUnpublished should be restored after normalization'
        );
    }

    public function testNormalizeThrowsDataObjectNormalizerException(): void
    {
        $this->expectException(DataObjectNormalizerException::class);

        $classDefinition = $this->createClassDefinition([
            'broken_field' => new ClassDefinition\Data\Input(),
        ]);

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => function () {
                throw new Exception('Something went wrong');
            },
        ]);

        $dataObject = $this->createConcreteObjectMock($classDefinition);

        $normalizer = $this->createNormalizer(fieldDefinitionService: $fieldDefinitionService);
        $normalizer->normalize($dataObject);
    }

    private function createClassDefinition(array $fieldDefinitions = []): ClassDefinition
    {
        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions($fieldDefinitions);
        $classDefinition->setAllowInherit(false);

        return $classDefinition;
    }

    private function createConcreteObjectMock(?ClassDefinition $classDefinition = null): Concrete
    {
        return $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition ?? $this->createClassDefinition(),
            'getId' => 1,
            'getParentId' => 0,
            'getCreationDate' => null,
            'getModificationDate' => null,
            'getType' => 'object',
            'getKey' => 'test',
            'getIndex' => 0,
            'getChildrenSortBy' => 'key',
            'getChildrenSortOrder' => 'asc',
            'getPath' => '/',
            'getRealFullPath' => '/test',
            'getUserOwner' => 1,
            'getUserModification' => 1,
            'getLocked' => null,
            'getClassId' => '1',
            'getClassName' => 'Test',
            'getPublished' => true,
            'get' => 'some_value',
        ]);
    }

    private function createNormalizer(
        ?FieldDefinitionServiceInterface $fieldDefinitionService = null,
        ?WorkflowServiceInterface $workflowService = null,
        ?DependencyServiceInterface $dependencyService = null,
        CalculatedFieldsIndexMode $calculatedFieldsIndexMode = CalculatedFieldsIndexMode::LIVE,
        ?CalculatedValueQueryStoreServiceInterface $calculatedValueQueryStoreService = null,
    ): DataObjectNormalizer {
        return new DataObjectNormalizer(
            $fieldDefinitionService ?? $this->makeEmpty(FieldDefinitionServiceInterface::class),
            $workflowService ?? $this->makeEmpty(WorkflowServiceInterface::class),
            $dependencyService ?? $this->makeEmpty(DependencyServiceInterface::class),
            $this->makeEmpty(
                CalculatedFieldsIndexModeResolverInterface::class,
                ['getMode' => $calculatedFieldsIndexMode]
            ),
            $calculatedValueQueryStoreService ?? $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class),
        );
    }

    private function createObjectWithCalculatedField(mixed $getExpectation): Concrete
    {
        $classDefinition = $this->createClassDefinition([
            'someInput' => (new Input())->setName('someInput'),
            'someCalculatedValue' => (new CalculatedValue())->setName('someCalculatedValue'),
        ]);

        return $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
            'getId' => 1,
            'getParentId' => 0,
            'getCreationDate' => null,
            'getModificationDate' => null,
            'getType' => 'object',
            'getKey' => 'test',
            'getIndex' => 0,
            'getChildrenSortBy' => 'key',
            'getChildrenSortOrder' => 'asc',
            'getPath' => '/',
            'getRealFullPath' => '/test',
            'getUserOwner' => 1,
            'getUserModification' => 1,
            'getLocked' => null,
            'getClassId' => '1',
            'getClassName' => 'Test',
            'getPublished' => true,
            'get' => $getExpectation,
        ]);
    }

    /**
     * Canary test for query_store mode: the calculated field's getter (and with it the
     * calculator) must NEVER be invoked - the value comes from the query store service.
     */
    public function testQueryStoreModeNeverExecutesTheCalculator(): void
    {
        // `get` must be called exactly once: for someInput. A call for the calculated
        // field would be the calculator executing.
        $dataObject = $this->createObjectWithCalculatedField(Expected::once('input value'));

        $queryStoreService = $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class, [
            'getValue' => Expected::once('stored value'),
        ]);

        // pass values through unchanged so the assertion can see them
        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => fn ($fieldDefinition, $value) => $value,
        ]);

        $normalizer = $this->createNormalizer(
            fieldDefinitionService: $fieldDefinitionService,
            calculatedFieldsIndexMode: CalculatedFieldsIndexMode::QUERY_STORE,
            calculatedValueQueryStoreService: $queryStoreService,
        );

        $result = $normalizer->normalize($dataObject);
        $standardFields = $result[FieldCategory::STANDARD_FIELDS->value];

        $this->assertSame('stored value', $standardFields['someCalculatedValue']);
        $this->assertSame('input value', $standardFields['someInput']);
    }

    /**
     * In live mode (the default) behavior is unchanged: the getter runs for every
     * field and the query store service is never consulted.
     */
    public function testLiveModeExecutesTheCalculatorAndSkipsTheQueryStore(): void
    {
        $dataObject = $this->createObjectWithCalculatedField(Expected::exactly(2, 'live value'));

        $queryStoreService = $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class, [
            'getValue' => Expected::never(),
        ]);

        $normalizer = $this->createNormalizer(
            calculatedFieldsIndexMode: CalculatedFieldsIndexMode::LIVE,
            calculatedValueQueryStoreService: $queryStoreService,
        );

        $normalizer->normalize($dataObject);
    }
}
