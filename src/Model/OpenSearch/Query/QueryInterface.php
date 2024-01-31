<?php

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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType;

interface QueryInterface
{
    public function getType(): QueryType;

    public function isEmpty(): bool;

    public function getParams(): array;

    public function toArray(): array;
}
