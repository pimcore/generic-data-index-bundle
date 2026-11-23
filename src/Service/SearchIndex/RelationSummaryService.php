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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use function method_exists;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\RelationSummaries;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\LightweightRelationLoadingInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\PreGetValueHookInterface;
use ReflectionMethod;
use function ucfirst;

/**
 * @internal
 */
final readonly class RelationSummaryService implements RelationSummaryServiceInterface
{
    public function getRelationSummaries(
        Concrete $dataObject,
        Data $fieldDefinition,
        string $key,
        bool $walkParents
    ): ?RelationSummaries {
        if (!$fieldDefinition instanceof LightweightRelationLoadingInterface
            || !$this->usesGeneratedGetter($dataObject, $key)
        ) {
            return null;
        }

        return $this->findSummaries($dataObject, $fieldDefinition, $key, $walkParents);
    }

    private function findSummaries(
        Concrete $dataObject,
        LightweightRelationLoadingInterface $fieldDefinition,
        string $key,
        bool $walkParents
    ): ?RelationSummaries {
        if ($dataObject->isLazyKeyLoaded($key)) {
            return null;
        }

        $summaries = $fieldDefinition->loadRelationSummaries($dataObject);
        if ($summaries !== [] || !$walkParents) {
            return new RelationSummaries((int) $dataObject->getId(), $summaries);
        }

        $parent = $dataObject->getNextParentForInheritance();
        if ($parent === null) {
            return new RelationSummaries((int) $dataObject->getId(), $summaries);
        }

        return $this->findSummaries($parent, $fieldDefinition, $key, true);
    }

    /**
     * The relation rows only describe what the generated getter returns through the generic get(); a getter,
     * a get() or a pre-get hook of the project (a model override or a custom parent class) may return something
     * else, so the value is then read as usual.
     */
    private function usesGeneratedGetter(Concrete $dataObject, string $key): bool
    {
        $getter = 'get' . ucfirst($key);
        if ($dataObject instanceof PreGetValueHookInterface || !method_exists($dataObject, $getter)) {
            return false;
        }

        if ((new ReflectionMethod($dataObject, 'get'))->getDeclaringClass()->getName() !== AbstractObject::class) {
            return false;
        }

        return (new ReflectionMethod($dataObject, $getter))->getDeclaringClass()->getName()
            === 'Pimcore\\Model\\DataObject\\' . ucfirst((string) $dataObject->getClassName());
    }
}
