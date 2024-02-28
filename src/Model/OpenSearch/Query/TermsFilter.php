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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;

final class TermsFilter extends BoolQuery
{
    public function __construct(
        private readonly string $field,
        /** @var (int|string)[] */
        private readonly array $terms,
    ) {
        parent::__construct([
            ConditionType::FILTER->value => [
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
