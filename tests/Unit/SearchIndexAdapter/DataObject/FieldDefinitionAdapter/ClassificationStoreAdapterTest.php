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
use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Psr\Log\AbstractLogger;
use ReflectionMethod;

/**
 * @internal
 */
final class ClassificationStoreAdapterTest extends Unit
{
    public function testExceptionIsThrownWhenWrongFieldDefinition()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $adapter = new ClassificationStoreAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $relation = new Checkbox();
        $adapter->setFieldDefinition($relation);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field definition must be an instance of ' . Classificationstore::class);
        $adapter->getIndexMapping();
    }

    public function testWarningIncludesTypeKeyIdGroupIdAndExceptionMessageWhenFieldDefinitionCannotBeResolved(): void
    {
        $adapter = new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );

        $resolverException = new Exception('unknown implementation for type input');
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => static function () use ($resolverException): never {
                throw $resolverException;
            },
        ]));

        $logger = $this->createCollectingLogger();
        $adapter->setLogger($logger);

        $key = new KeyGroupRelation();
        $key->setType('input');
        $key->setGroupId(9);
        $key->setKeyId(42);

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getFieldDefinitionForKey');
        $result = $method->invoke($adapter, $key);

        $this->assertNull($result);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame(
            'Could not get field definition for type input for key 42 in group 9: unknown implementation for type input',
            $logger->records[0]['message']
        );
    }

    private function createCollectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var array<int, array{level: string, message: string}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }
}
