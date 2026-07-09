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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Entity;

use Codeception\Test\Unit;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;

/**
 * The ORM metadata must match the schema created by the Installer, otherwise a
 * doctrine schema update regenerates the queue table without the auto increment
 * on `id` (every row is inserted with id 0, so `INSERT IGNORE` keeps only one
 * row) and without the unique index the queue deduplication relies on.
 *
 * @see https://github.com/pimcore/generic-data-index-bundle/issues/432
 *
 * @internal
 */
final class IndexQueueTest extends Unit
{
    public function testIdUsesGeneratedValue(): void
    {
        $metadata = $this->loadClassMetadata();

        $this->assertNotSame(
            ClassMetadata::GENERATOR_TYPE_NONE,
            $metadata->generatorType,
            'IndexQueue::$id must declare #[ORM\GeneratedValue] so schema updates keep the auto increment'
        );
    }

    public function testElementIdTypeUniqueConstraintIsDeclared(): void
    {
        $metadata = $this->loadClassMetadata();

        $uniqueConstraints = $metadata->table['uniqueConstraints'] ?? [];
        $constraintName = IndexQueue::TABLE . '_element_id_type';

        $this->assertArrayHasKey(
            $constraintName,
            $uniqueConstraints,
            'IndexQueue must declare the unique constraint on (elementId, elementType) the queue deduplication relies on'
        );
        $this->assertSame(
            ['elementId', 'elementType'],
            $uniqueConstraints[$constraintName]['columns']
        );
    }

    private function loadClassMetadata(): ClassMetadata
    {
        $metadata = new ClassMetadata(IndexQueue::class);
        $metadata->initializeReflection(new RuntimeReflectionService());

        (new AttributeDriver([]))->loadMetadataForClass(IndexQueue::class, $metadata);

        return $metadata;
    }
}
