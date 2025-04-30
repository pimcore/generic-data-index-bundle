<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Traits\QueryObjectsToArrayTrait;

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
        if ($withType) {
            return [$this->type => $this->getParams()];
        }

        return $this->getParams();
    }
}
