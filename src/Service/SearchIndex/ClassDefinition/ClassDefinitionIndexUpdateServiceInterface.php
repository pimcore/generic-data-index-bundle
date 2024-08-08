<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition;


use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface ClassDefinitionIndexUpdateServiceInterface
{
    /**
     * @throws ClassDefinitionIndexUpdateFailedException
     */
    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged = false,
        bool $enqueueItems = false,
    ): bool;
}