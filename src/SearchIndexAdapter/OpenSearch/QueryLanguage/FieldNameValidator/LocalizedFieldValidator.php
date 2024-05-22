<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameValidator;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameValidatorInterface;
use Pimcore\Tool;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 1)]
final readonly class LocalizedFieldValidator implements FieldNameValidatorInterface
{
    private string $defaultLocale;

    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService,
        string $defaultLanguage = null
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
