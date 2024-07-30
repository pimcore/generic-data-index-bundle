<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Carbon\Carbon;
use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType;
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
        $this->startDate = is_int($startTimestamp) ? Carbon::createFromTimestamp($startTimestamp) : $startTimestamp;
        $this->endDate = is_int($endTimestamp) ? Carbon::createFromTimestamp($endTimestamp) : $endTimestamp;
        $this->onDate = is_int($onTimestamp) ? Carbon::createFromTimestamp($onTimestamp) : $onTimestamp;
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
            if (!is_int($value)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid value for parameter %s, expected integer, got %s',
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
        if ($this->onDate) {
            $params['gte'] = $this->getStartOfDay($this->onDate)->format(DateTimeInterface::ATOM);
            $params['lte'] =  $this->getEndOfDay($this->onDate)->format(DateTimeInterface::ATOM);
        } else {
            if ($this->startDate) {
                $params['gte'] = $this->getStartOfDay($this->startDate)->format(DateTimeInterface::ATOM);
            }
            if ($this->endDate) {
                $params['lte'] = $this->getEndOfDay($this->endDate)->format(DateTimeInterface::ATOM);
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
        return $this->endDate->getTimestamp();
    }

    public function getStartTimestamp(): ?int
    {
        return $this->startDate->getTimestamp();
    }

    public function getOnTimestamp(): ?int
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
