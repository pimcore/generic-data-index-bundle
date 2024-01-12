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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;

class DefaultAdapter implements FieldDefinitionAdapterInterface
{
    /** @var Data */
    protected Data $fieldDefinition;

    public function __construct(
        protected SearchIndexConfigService $searchIndexConfigService,
    )
    {
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

    /**
     * @return array
     */
    public function getOpenSearchMapping(): array
    {
        $fields = [
            'raw' => [
                'type' => AttributeType::KEYWORD->value
            ]
        ];

        $searchAttributes = $this->searchIndexConfigService->getSearchSettings()['search_analyzer_attributes'][AttributeType::KEYWORD->value]['fields'] ?? [];
        if (!empty($searchAttributes)) {
            $fields = array_merge($searchAttributes, $fields);
        }

        return [
            $this->fieldDefinition->getName(),
            [
                'type' => AttributeType::KEYWORD->value,
                'fields' => $fields
            ]
        ];
    }

    public function getIndexData(Concrete $object): mixed
    {
        $value = $this->doGetIndexDataValue($object);

        return $value ?: null;
    }

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $value = $this->fieldDefinition->getDataForSearchIndex($object);

        if($this->fieldDefinition instanceof NormalizerInterface) {
            $value = $this->fieldDefinition->normalize($value);
        }

        /* @phpstan-ignore-next-line */
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string)$value;
    }


    protected function doGetRawIndexDataValue(Concrete $object): mixed
    {
        /** @var string $getter */
        $getter = 'get' . ucfirst($this->fieldDefinition->getName());

        return $object->$getter();
    }
}
