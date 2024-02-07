<?php
declare(strict_types=1);

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

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;

abstract class AbstractAdapter implements AdapterInterface
{
    private Data $fieldDefinition;

    public function __construct(
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly FieldDefinitionServiceInterface $fieldDefinitionService
    ) {
    }

    abstract public function getOpenSearchMapping(): array;

    public function setFieldDefinition(Data $fieldDefinition): self
    {
        $this->fieldDefinition = $fieldDefinition;

        return $this;
    }

    public function getFieldDefinition(): Data
    {
        return $this->fieldDefinition;
    }

    public function getOpenSearchAttributeName(): string
    {
        return $this->fieldDefinition->getName();
    }

    public function getFieldDefinitionService(): FieldDefinitionServiceInterface
    {
        return $this->fieldDefinitionService;
    }
}
