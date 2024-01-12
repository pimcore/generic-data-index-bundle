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
use Pimcore\Bundle\PortalEngineBundle\Service\Element\NameExtractorService;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

class ManyToOneRelationAdapter extends DefaultAdapter
{
    /** @var NameExtractorService */
    protected $nameExtractorService;

    /**
     * @param NameExtractorService $nameExtractorService
     *
     * @required
     */
    public function setNameExtractorService(NameExtractorService $nameExtractorService): void
    {
        $this->nameExtractorService = $nameExtractorService;
    }

    /**
     * @return array
     */
    public function getOpenSearchMapping(): array
    {
        $nameFields = [
            'raw' => [
                'type' => AttributeType::KEYWORD->value,
            ],
        ];

        $searchAttributes = $this->searchIndexConfigService->getSearchSettings()['search_analyzer_attributes'][AttributeType::KEYWORD->value]['fields'] ?? [];
        if (!empty($searchAttributes)) {
            $nameFields = array_merge($searchAttributes, $nameFields);
        }

        return [
            $this->fieldDefinition->getName(),
            [
                'properties' => [
                    'id' => [
                        'type' => AttributeType::LONG->value,
                    ],
                    'type' => [
                        'type' => AttributeType::TEXT->value,
                    ],
                    'name' => [
                        'type' => AttributeType::TEXT->value,
                        'fields' => $nameFields,
                    ],
                ],
            ],
        ];
    }

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        /** @var mixed $value */
        $value = [];
        /** @var ElementInterface $element */
        $element = $this->doGetRawIndexDataValue($object);

        if ($element instanceof Concrete || $element instanceof Asset) {
            $value = $this->getArrayValuesByElement($element);
        }

        return $value;
    }

    /**
     * @param ElementInterface $element
     *
     * @return array
     */
    protected function getArrayValuesByElement($element)
    {
        return [
            'id' => $element->getId(),
            'name' => $this->nameExtractorService->extractName($element),
            'type' => Service::getElementType($element),
        ];
    }
}
