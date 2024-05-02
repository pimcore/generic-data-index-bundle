<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;

/**
 * @internal
 */
interface IndexEntityServiceInterface
{
    public function getByEntityName(string $entityName): IndexEntity;

    public function getByIndexName(string $indexName): IndexEntity;
}