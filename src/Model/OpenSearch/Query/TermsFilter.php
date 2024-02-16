<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

final class TermsFilter extends BoolQuery
{
    public function __construct(
        private readonly string $field,
        /** @var (int|string)[] */
        private readonly array $terms,
    ) {
        parent::__construct([
            'filter' => [
                'terms' => [
                    $this->field => $this->terms,
                ],
            ],
        ]);
    }

    public function getField(): string
    {
        return $this->field;
    }

    /** @return (int|string)[] */
    public function getTerms(): array
    {
        return $this->terms;
    }
}