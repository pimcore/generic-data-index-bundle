<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Model\DataObject\ClassDefinition\Data;

abstract class AbstractAdapter implements FieldDefinitionAdapterInterface
{
    protected Data $fieldDefinition;

    public function __construct(
        protected readonly SearchIndexConfigService $searchIndexConfigService,
    ) {
    }

    public function setFieldDefinition(Data $fieldDefinition): self
    {
        $this->fieldDefinition = $fieldDefinition;

        return $this;
    }

    public function getFieldDefinition(): Data
    {
        return $this->fieldDefinition;
    }

    abstract public function getOpenSearchMapping(): array;

    public function getOpenSearchAttributeName(): string
    {
        return $this->fieldDefinition->getName();
    }
}
