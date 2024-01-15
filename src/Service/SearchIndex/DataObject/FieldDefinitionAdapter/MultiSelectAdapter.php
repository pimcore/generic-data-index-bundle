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

use Pimcore\Bundle\GenericDataIndexBundle\Service\DataObject\SelectOptionsService;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Contracts\Service\Attribute\Required;

class MultiSelectAdapter extends DefaultAdapter
{
    protected SelectOptionsService $selectOptionsService;

    /** @var Data\Multiselect */
    protected Data $fieldDefinition;

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $values = [];
        $options = $this->fieldDefinition->getOptions();
        $selectValues = $this->doGetRawIndexDataValue($object);

        if (is_array($selectValues) && is_array($options)) {
            foreach ($selectValues as $selectValue) {
                $values[] = $this->selectOptionsService->getKeyByValue($selectValue, $options);
            }
        }

        return $values;
    }

    #[Required]
    public function setSelectOptionsService(SelectOptionsService $selectOptionsService): void
    {
        $this->selectOptionsService = $selectOptionsService;
    }
}
