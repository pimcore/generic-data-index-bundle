<?php
declare(strict_types=1);

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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    private Data $fieldDefinition;

    public function __construct(
        protected SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected FieldDefinitionServiceInterface $fieldDefinitionService,
    ) {
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
        callable $callback = null
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

        $parentValue = $callback ? $callback($parent, $key, $language) : $parent->get($key, $language);
        if (!$this->fieldDefinition->isEmpty($parentValue)) {
            return [$path => ['originId' => $parent->getId()]];
        }

        return $this->getInheritedData($parent, $objectId, $value, $key, $language, $callback);
    }
}
