<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameValidator;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameValidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 2)]
final readonly class RelationValidator implements FieldNameValidatorInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function validateFieldName(
        string $originalFieldName,
        string $fieldName,
        array $indexMapping,
        ?IndexEntity $targetEntity = null
    ): ?string
    {
        if ($targetEntity) {
            $isValidRelationField = false;
            foreach(['assets', 'asset','object','document'] as $type) {

                if (str_ends_with($fieldName, '.' . $type)) {
                    $isValidRelationField = true;
                    break;
                }

                $relationField = $fieldName . '.' . $type;
                if ($this->mappingAnalyzerService->fieldPathExists($relationField, $indexMapping)) {
                    $isValidRelationField = true;
                    break;
                }
            }

            if (!$isValidRelationField) {
                return sprintf(
                    'Field `%s` is not a valid relation field.',
                    $originalFieldName
                );
            }
        }

        return null;
    }

}