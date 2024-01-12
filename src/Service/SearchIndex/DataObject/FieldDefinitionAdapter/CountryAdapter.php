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

use Pimcore\Bundle\PortalEngineBundle\Service\DataObject\CountryNameService;
use Pimcore\Model\DataObject\Concrete;

class CountryAdapter extends SelectAdapter
{
    /** @var CountryNameService */
    #protected $countryNameService;

    /**
     * @param CountryNameService $countryNameService
     * @required
     */
    #public function setCountryNameService(CountryNameService $countryNameService): void
    #{
    #    $this->countryNameService = $countryNameService;
    #}

    #protected function doGetIndexDataValue(Concrete $object): mixed
    #{
    #    return $this->countryNameService->getCountryName($this->doGetRawIndexDataValue($object));
    #}
}
