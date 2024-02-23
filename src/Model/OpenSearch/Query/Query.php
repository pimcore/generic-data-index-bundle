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

final class Query implements QueryInterface
{
    public function __construct(
        private readonly string $type,
        private readonly array $params = [],
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isEmpty(): bool
    {
        return empty($this->params);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function toArray(): array
    {
        return $this->params;
    }
}
