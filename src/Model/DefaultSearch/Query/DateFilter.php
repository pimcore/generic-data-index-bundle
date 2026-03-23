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

use Carbon\Carbon;
use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;

final readonly class DateFilter implements QueryInterface
{
    public const PARAM_START = 'start';

    public const PARAM_END = 'end';

    public const PARAM_ON = 'on';

    private Carbon|null $startDate;

    private Carbon|null $endDate;

    private Carbon|null $onDate;

    public function __construct(
        private string $field,
        int|Carbon|null $startTimestamp = null,
        int|Carbon|null $endTimestamp = null,
        int|Carbon|null $onTimestamp = null,
        private bool $roundToDay = true,
    ) {
        $this->startDate = is_int($startTimestamp) ?
            Carbon::createFromTimestamp($startTimestamp, date_default_timezone_get()) : $startTimestamp;
        $this->endDate = is_int($endTimestamp) ?
            Carbon::createFromTimestamp($endTimestamp, date_default_timezone_get()) : $endTimestamp;
        $this->onDate = is_int($onTimestamp) ?
            Carbon::createFromTimestamp($onTimestamp, date_default_timezone_get()) : $onTimestamp;
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

        foreach ($params as $key => $value) {
            if (!is_int($value) && (!$value instanceof Carbon)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid value for parameter %s, expected integer or instanceof of Carbon, got %s',
                        $key,
                        gettype($value)
                    )
                );
            }
        }

        return new self(
            $field,
            $params[self::PARAM_START] ?? null,
            $params[self::PARAM_END] ?? null,
            $params[self::PARAM_ON] ?? null,
            $roundToDay
        );
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
        $params = ['format' => "yyyy-MM-dd'T'HH:mm:ssz"];

        if ($this->onDate) {
            $params['gte'] = $this->getStartOfDay($this->onDate)->format(DateTimeInterface::ATOM);
            $params['lte'] =  $this->getEndOfDay($this->onDate)->format(DateTimeInterface::ATOM);

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

    public function getEndTimestamp(): int
    {
        return $this->endDate->getTimestamp();
    }

    public function getStartTimestamp(): int
    {
        return $this->startDate->getTimestamp();
    }

    public function getOnTimestamp(): int
    {
        return $this->onDate->getTimestamp();
    }

    public function getEndDate(): Carbon
    {
        return $this->endDate;
    }

    public function getStartDate(): Carbon
    {
        return $this->startDate;
    }

    public function getOnDate(): Carbon
    {
        return $this->onDate;
    }

    private function addStartParams(array $params): array
    {
        if (!$this->startDate) {
            return $params;
        }

        if ($this->endDate) {
            $params['gte'] = $this->getStartOfDay($this->startDate)->format(DateTimeInterface::ATOM);

            return $params;
        }

        $params['gte'] = $this->getEndOfDay($this->startDate)->format(DateTimeInterface::ATOM);

        return $params;
    }

    private function addEndParams(array $params): array
    {
        if (!$this->endDate) {
            return $params;
        }

        if ($this->startDate) {
            $params['lte'] = $this->getEndOfDay($this->endDate)->format(DateTimeInterface::ATOM);

            return $params;
        }

        $params['lte'] = $this->getStartOfDay($this->endDate)->format(DateTimeInterface::ATOM);

        return $params;
    }

    private function getStartOfDay(Carbon $date): Carbon
    {
        if (!$this->roundToDay) {
            return $date;
        }

        return $date->startOfDay();
    }

    private function getEndOfDay(Carbon $date): Carbon
    {
        if (!$this->roundToDay) {
            return $date;
        }

        return $date->endOfDay();
    }

    public function isRoundToDay(): bool
    {
        return $this->roundToDay;
    }
}
