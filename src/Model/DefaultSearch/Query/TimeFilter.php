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

final readonly class TimeFilter implements QueryInterface
{
    public function __construct(
        private string $field,
        private string|null $startTime = null,
        private string|null $endTime = null,
        private string|null $onTime = null,
    ) {
    }

    public function getType(): string
    {
        return 'range';
    }

    public function isEmpty(): bool
    {
        return empty($this->getParams());
    }

    public function getParams(): array
    {
        $params = [];

        if ($this->onTime) {
            $params['gte'] = $this->onTime;
            $params['lte'] =  $this->onTime;

            return [$this->field => $params];
        }

        $params = $this->addStartParams($params);
        $params = $this->addEndParams($params);

        return [$this->field => $params];
    }

    public function toArray(bool $withType = false): array
    {
        if ($withType) {
            return [$this->getType() => $this->getParams()];
        }

        return $this->getParams();
    }

    public function getField(): string
    {
        return $this->field;
    }

    private function addStartParams(array $params): array
    {
        if (!$this->startTime) {
            return $params;
        }

        $params['gte'] = $this->startTime;

        return $params;
    }

    private function addEndParams(array $params): array
    {
        if (!$this->endTime) {
            return $params;
        }

        $params['lte'] = $this->endTime;

        return $params;
    }
}
