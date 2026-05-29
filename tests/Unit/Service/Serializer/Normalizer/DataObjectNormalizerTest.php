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

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectNormalizerException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Dependency\DependencyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\DataObjectNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
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

        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions([]);
        $classDefinition->setAllowInherit(false);

        $dataObject = $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
            'getId' => 1,
            'getParentId' => 0,
            'getCreationDate' => null,
            'getModificationDate' => null,
            'getType' => 'object',
            'getKey' => 'test',
            'getIndex' => 0,
            'getChildrenSortBy' => null,
            'getChildrenSortOrder' => null,
            'getPath' => '/',
            'getRealFullPath' => '/test',
            'getUserOwner' => 1,
            'getUserModification' => 1,
            'getLocked' => null,
            'getClassId' => '1',
            'getClassName' => 'Test',
            'getPublished' => true,
        ]);

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

        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions([
            'broken_field' => new ClassDefinition\Data\Input(),
        ]);
        $classDefinition->setAllowInherit(false);

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => function () {
                throw new Exception('Normalization failed');
            },
        ]);

        $dataObject = $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
            'get' => 'some_value',
        ]);

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

        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions([
            'test_field' => new ClassDefinition\Data\Input(),
        ]);
        $classDefinition->setAllowInherit(false);

        $hideUnpublishedDuringNormalization = null;

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => function () use (&$hideUnpublishedDuringNormalization) {
                $hideUnpublishedDuringNormalization = AbstractObject::getHideUnpublished();

                return 'normalized_value';
            },
        ]);

        $dataObject = $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
            'get' => 'some_value',
        ]);

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

        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions([
            'broken_field' => new ClassDefinition\Data\Input(),
        ]);
        $classDefinition->setAllowInherit(false);

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'normalizeValue' => function () {
                throw new Exception('Something went wrong');
            },
        ]);

        $dataObject = $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
            'get' => 'value',
        ]);

        $normalizer = $this->createNormalizer(fieldDefinitionService: $fieldDefinitionService);
        $normalizer->normalize($dataObject);
    }

    private function createNormalizer(
        ?FieldDefinitionServiceInterface $fieldDefinitionService = null,
        ?WorkflowServiceInterface $workflowService = null,
        ?DependencyServiceInterface $dependencyService = null,
    ): DataObjectNormalizer {
        return new DataObjectNormalizer(
            $fieldDefinitionService ?? $this->makeEmpty(FieldDefinitionServiceInterface::class),
            $workflowService ?? $this->makeEmpty(WorkflowServiceInterface::class),
            $dependencyService ?? $this->makeEmpty(DependencyServiceInterface::class),
        );
    }
}
