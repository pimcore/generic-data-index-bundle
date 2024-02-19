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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort;

final class FieldSort
{
    public const ORDER_ASC = 'asc';

    public const ORDER_DESC = 'desc';

    public function __construct(
        private string $field,
        private string $order = self::ORDER_ASC,
        private array $params = []
    ) {
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

    public function getOrder(): string
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
