<?php
declare(strict_types=1);

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
    ): ?string
    {
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