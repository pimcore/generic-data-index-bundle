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

final class Filter extends BoolQuery
{
    public function __construct(
        private readonly array $filterParams,
    ) {
        parent::__construct([
            'filter' => $filterParams,
        ]);
    }

    public function getFilterParams(): array
    {
        return $this->filterParams;
    }
}
