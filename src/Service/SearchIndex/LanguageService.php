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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Tool;

class LanguageService
{
    protected array $validLanguages = [];

    public function setValidLanguages(array $argLanguages): void
    {
        $this->validLanguages = $argLanguages;
    }

    public function getValidLanguages(): array
    {
        if (empty($this->validLanguages)) {
            return Tool::getValidLanguages();
        }

        return $this->validLanguages;
    }
}
