<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
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
