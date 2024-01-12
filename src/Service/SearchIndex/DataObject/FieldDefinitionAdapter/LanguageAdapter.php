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

use Pimcore\Bundle\PortalEngineBundle\Service\DataObject\LanguageNameService;
use Pimcore\Model\DataObject\Concrete;

class LanguageAdapter extends SelectAdapter
{
    /** @var LanguageNameService */
    #protected $languageNameService;

    /**
     * @param LanguageNameService $languageNameService
     * @required
     */
    #public function setLanguageNameService(LanguageNameService $languageNameService): void
    #{
    #    $this->languageNameService = $languageNameService;
    #}

    #protected function doGetIndexDataValue(Concrete $object): mixed
    #{
    #    return $this->languageNameService->getLanguageName($this->doGetRawIndexDataValue($object));
    #}
}
