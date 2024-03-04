<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\WildcardFilterMode;

final class WildcardFilter extends BoolQuery implements AsSubQueryInterface
{
    public function __construct(
        private readonly string $field,
        private readonly string $term,
        private readonly WildcardFilterMode $defaultWildcardMode = WildcardFilterMode::BOTH,
        private readonly bool $caseInsensitive = true,
    )
    {
        parent::__construct([
            ConditionType::FILTER->value => $this->getWildcardQueryArray(),
        ]);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function isCaseInsensitive(): bool
    {
        return $this->caseInsensitive;
    }

    public function toArrayAsSubQuery(): array
    {
        return $this->getWildcardQueryArray();
    }

    private function getWildcardQueryArray(): array
    {
        $term = $this->term;

        if ($term !== '' && !str_contains($term, '*')) {
            if($this->defaultWildcardMode === WildcardFilterMode::BOTH) {
                $term = "*{$term}*";
            } elseif($this->defaultWildcardMode === WildcardFilterMode::PREFIX) {
                $term = "*{$term}";
            } elseif($this->defaultWildcardMode === WildcardFilterMode::SUFFIX) {
                $term = "{$term}*";
            }
        }

        return [
            'wildcard' => [
                $this->field => [
                    'value' => $term,
                    'case_insensitive' => $this->caseInsensitive,
                ],
            ],
        ];
    }
}