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

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\QueryObjectsToArrayTrait;

final class Query implements QueryInterface
{
    use QueryObjectsToArrayTrait;

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

    public function toArray(bool $withType = false): array
    {
        $result = $this->convertQueryObjectsToArray($this->getParams());

        if ($withType) {
            return [$this->type => $result];
        }
        return $result;
    }
}
