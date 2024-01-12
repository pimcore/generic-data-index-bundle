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

class CountryMultiSelectAdapter extends MultiSelectAdapter
{
    /** @var CountryNameService */
   # protected $countryNameService;

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
    #    /** @var array $values */
    #    $values = [];
    #    /** @var array $countryCodes */
    #    $countryCodes = $this->doGetRawIndexDataValue($object);

        #if (is_array($countryCodes)) {
        #    foreach ($countryCodes as $countryCode) {
        #        $values[] = $this->countryNameService->getCountryName($countryCode);
        #    }
        #}

        #return $values;
    #}
}
