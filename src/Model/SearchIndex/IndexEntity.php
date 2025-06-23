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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;

final readonly class IndexEntity
{
    public function __construct(
        private string $entityName,
        private string $indexName,
        private ?IndexType $indexType,
    ) {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getIndexType(): ?IndexType
    {
        return $this->indexType;
    }
}
