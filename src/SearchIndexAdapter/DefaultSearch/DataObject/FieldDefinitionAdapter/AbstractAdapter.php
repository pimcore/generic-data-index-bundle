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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAdapter implements AdapterInterface
{
    private Data $fieldDefinition;

    private RuntimeCacheResolverInterface $runtimeCacheResolver;

    public function __construct(
        protected SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected FieldDefinitionServiceInterface $fieldDefinitionService,
    ) {
    }

    #[Required]
    public function setRuntimeCacheResolver(RuntimeCacheResolverInterface $runtimeCacheResolver): void
    {
        $this->runtimeCacheResolver = $runtimeCacheResolver;
    }

    abstract public function getIndexMapping(): array;

    public function setFieldDefinition(Data $fieldDefinition): self
    {
        $this->fieldDefinition = $fieldDefinition;

        return $this;
    }

    public function getFieldDefinition(): Data
    {
        return $this->fieldDefinition;
    }

    public function getIndexAttributeName(): string
    {
        return $this->fieldDefinition->getName();
    }

    public function getFieldDefinitionService(): FieldDefinitionServiceInterface
    {
        return $this->fieldDefinitionService;
    }

    public function normalize(mixed $value): mixed
    {
        if ($this->fieldDefinition instanceof NormalizerInterface) {
            return $this->fieldDefinition->normalize($value);
        }

        return $value;
    }

    /**
     * @throws Exception
     */
    public function getInheritedData(
        Concrete $dataObject,
        int $objectId,
        mixed $value,
        string $key,
        ?string $language = null,
        ?callable $callback = null
    ): array {
        if (!$this->fieldDefinition->isEmpty($value)) {
            return [];
        }

        $path = $key;
        if ($language !== null) {
            $path .= '.' . $language;
        }

        $parent = $dataObject->getNextParentForInheritance();
        if ($parent === null) {
            return $objectId === $dataObject->getId() ? [] : [$path => ['originId' => $dataObject->getId()]];
        }

        return [$path => ['originId' => $this->resolveInheritanceOrigin($parent, $key, $language, $callback)]];
    }

    /**
     * First ancestor (starting at $parent) with a non-empty value for the field, or the
     * topmost ancestor when the whole chain is empty. The result only depends on the
     * parent chain, so it is memoized per (parent, key, language) — siblings sharing a
     * parent (variants) resolve it once per batch instead of walking the chain each.
     * Runtime-cache scoped, so the cleanup between queue batches bounds staleness.
     * Callback-based resolution (object bricks) carries per-call context and is not
     * memoized.
     */
    private function resolveInheritanceOrigin(
        Concrete $parent,
        string $key,
        ?string $language,
        ?callable $callback
    ): int {
        $cacheKey = null;
        if ($callback === null) {
            $cacheKey = sprintf(
                'gdi_inheritance_origin_%d_%s_%s_%d',
                $parent->getId(),
                $key,
                $language ?? '',
                $parent->getModificationDate() ?? 0
            );
            if ($this->runtimeCacheResolver->isRegistered($cacheKey)) {
                return $this->runtimeCacheResolver->load($cacheKey);
            }
        }

        $parentValue = $callback ? $callback($parent, $key, $language) : $parent->get($key, $language);

        if (!$this->fieldDefinition->isEmpty($parentValue)) {
            $originId = $parent->getId();
        } else {
            $grandParent = $parent->getNextParentForInheritance();
            $originId = $grandParent === null
                ? $parent->getId()
                : $this->resolveInheritanceOrigin($grandParent, $key, $language, $callback);
        }

        if ($cacheKey !== null) {
            $this->runtimeCacheResolver->save($originId, $cacheKey);
        }

        return $originId;
    }
}
