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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
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
        if ($withType) {
            return [$this->type => $this->getParams()];
        }

        return $this->getParams();
    }

    public static function createFromArray(array $array): Query
    {
        if (count($array) !== 1) {
            throw new InvalidArgumentException('Invalid query array. Expected exactly one key-value pair.');
        }

        $type = array_key_first($array);
        $params = array_values($array)[0];

        if (!is_string($type)) {
            throw new InvalidArgumentException('Invalid query array. Expected query type as key.');
        }

        if (!is_array($params)) {
            throw new InvalidArgumentException('Invalid query array. Expected query parameters as value.');
        }

        return new self($type, $params);
    }
}
