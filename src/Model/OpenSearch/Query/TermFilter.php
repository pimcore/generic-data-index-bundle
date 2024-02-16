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

final class TermFilter extends BoolQuery
{
    public function __construct(
        private readonly string $field,
        private readonly string|int $term,
    ) {
        parent::__construct([
            'filter' => [
                'term' => [
                    $this->field => $this->term,
                ],
            ],
        ]);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getTerm(): string|int
    {
        return $this->term;
    }
}
