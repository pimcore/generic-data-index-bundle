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

class SelectAdapter extends DefaultAdapter
{
    protected SelectOptionsService $selectOptionsService;

    /** @var Data\Select */
    protected Data $fieldDefinition;

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $value = null;
        $options = $this->fieldDefinition->getOptions();

        if (is_array($options)) {
            $value = $this->selectOptionsService->getKeyByValue($this->doGetRawIndexDataValue($object), $options);
        }

        return $value;
    }

    #[Required]
    public function setSelectOptionsService(SelectOptionsService $selectOptionsService): void
    {
        $this->selectOptionsService = $selectOptionsService;
    }
}
