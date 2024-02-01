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

use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Tool;

/**
 * @internal
 */
final class LanguageService implements LanguageServiceInterface
{
    public function __construct(
        private readonly LocaleServiceInterface $localeService,
    ) {
    }

    private array $validLanguages = [];

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

    public function getNewLanguages(array $validLanguages): array
    {
        $newLanguages = [];

        foreach ($validLanguages as $language) {
            if ($this->localeService->isLocale($language)) {
                $newLanguages[] = $language;
            }
        }

        return $newLanguages;
    }
}
