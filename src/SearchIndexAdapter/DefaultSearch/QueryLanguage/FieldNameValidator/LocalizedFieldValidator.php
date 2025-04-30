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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameValidator;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameValidatorInterface;
use Pimcore\Tool;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @internal
 */
#[AsTaggedItem(priority: 1)]
final readonly class LocalizedFieldValidator implements FieldNameValidatorInterface
{
    private string $defaultLocale;

    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService,
        ?string $defaultLanguage = null
    ) {
        $this->defaultLocale = $defaultLanguage ?? Tool::getDefaultLanguage();
    }

    public function validateFieldName(
        string $originalFieldName,
        string $fieldName,
        array $indexMapping,
        ?IndexEntity $targetEntity = null
    ): ?string {
        $defaultLocaleSubField = $fieldName . '.' . $this->defaultLocale;
        if ($this->mappingAnalyzerService->fieldPathExists($defaultLocaleSubField, $indexMapping)) {
            return sprintf(
                'Field `%s` is localized - please specify a language (e.g. `%s.%s`)',
                $originalFieldName,
                $originalFieldName,
                $this->defaultLocale
            );
        }

        return null;
    }
}
