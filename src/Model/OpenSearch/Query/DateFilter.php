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

use Carbon\Carbon;
use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;

final class DateFilter implements QueryInterface
{
    public const PARAM_START = 'start';

    public const PARAM_END = 'end';

    public const PARAM_ON = 'on';

    public function __construct(
        private readonly string $field,
        private readonly ?int $startTimestamp = null,
        private readonly ?int $endTimestamp = null,
        private readonly ?int $onTimestamp = null,
        private readonly bool $roundToDay = true,
    ) {
    }

    public static function createFromArray(string $field, array $params, bool $roundToDay = true): DateFilter
    {
        $allowedParams = [
            self::PARAM_END,
            self::PARAM_START,
            self::PARAM_ON,
        ];

        if (count(array_diff(array_keys($params), $allowedParams)) > 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid parameters for date filter (only %s are allowed): %s',
                    implode(', ', $allowedParams),
                    implode(', ', array_diff(array_keys($params), $allowedParams))
                )
            );
        }

        return new self(
            $field,
            $params[self::PARAM_START] ?? null,
            $params[self::PARAM_END] ?? null,
            $params[self::PARAM_ON] ?? null,
            $roundToDay
        );
    }

    public function getType(): QueryType|string
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
        if ($this->onTimestamp) {
            $params['gte'] = date(DateTimeInterface::ATOM, $this->getStartOfDay($this->onTimestamp));
            $params['lte'] = date(DateTimeInterface::ATOM, $this->getEndOfDay($this->onTimestamp));
        } else {
            if ($this->startTimestamp) {
                $params['gt'] = date(DateTimeInterface::ATOM, $this->getStartOfDay($this->startTimestamp));
            }
            if ($this->endTimestamp) {
                $params['lt'] = date(DateTimeInterface::ATOM, $this->getEndOfDay($this->endTimestamp));
            }
        }

        return [
            $this->field => $params,
        ];
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

    public function getEndTimestamp(): ?int
    {
        return $this->endTimestamp;
    }

    public function getStartTimestamp(): ?int
    {
        return $this->startTimestamp;
    }

    public function getOnTimestamp(): ?int
    {
        return $this->onTimestamp;
    }

    private function getStartOfDay(int $timestamp): int
    {
        if (!$this->roundToDay) {
            return $timestamp;
        }

        return Carbon::createFromTimestamp($timestamp)->startOfDay()->timestamp;
    }

    private function getEndOfDay(int $timestamp): int
    {
        if (!$this->roundToDay) {
            return $timestamp;
        }

        return Carbon::createFromTimestamp($timestamp)->endOfDay()->timestamp;
    }

    public function isRoundToDay(): bool
    {
        return $this->roundToDay;
    }
}
