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

class LanguageMultiSelectAdapter extends MultiSelectAdapter
{
    /** @var LanguageNameService */
    protected $languageNameService;

    /**
     * @param LanguageNameService $languageNameService
     * @required
     */
    public function setLanguageNameService(LanguageNameService $languageNameService): void
    {
        $this->languageNameService = $languageNameService;
    }


    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        /** @var array $values */
        $values = [];
        /** @var array $languageCodes */
        $languageCodes = $this->doGetRawIndexDataValue($object);

        if (is_array($languageCodes)) {
            foreach ($languageCodes as $languageCode) {
                $values[] = $this->languageNameService->getLanguageName($languageCode);
            }
        }

        return $values;
    }
}
