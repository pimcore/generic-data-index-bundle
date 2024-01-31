<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort;

class FieldSort
{
    public const ASC = 'asc';
    public const DESC = 'desc';

    public function __construct(
        private string $field,
        private ?string $order = null,
        private array $params = []
    )
    {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): FieldSort
    {
        $this->field = $field;
        return $this;
    }

    public function getOrder(): ?string
    {
        return $this->order;
    }

    public function setOrder(?string $order): FieldSort
    {
        $this->order = $order;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): FieldSort
    {
        $this->params = $params;
        return $this;
    }

    public function toArray(): array
    {
        if ($this->order) {
            $this->params['order'] = $this->order;
        }

        return [
            $this->field => $this->params,
        ];
    }
}
