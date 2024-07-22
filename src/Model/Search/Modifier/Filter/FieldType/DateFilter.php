<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType;

use Carbon\Carbon;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class DateFilter implements SearchModifierInterface
{
    private Carbon|null $startDate;
    private Carbon|null $endDate;
    private Carbon|null $onDate;

    public function __construct(
        private string $field,
        int|Carbon|null $startDate = null,
        int|Carbon|null $endDate = null,
        int|Carbon|null $onDate = null,
        private bool $roundToDay = true,
        private bool $enablePqlFieldNameResolution = true,
    )
    {
        $this->startDate = is_int($startDate) ? Carbon::createFromTimestamp($startDate) : $startDate;
        $this->endDate = is_int($endDate) ? Carbon::createFromTimestamp($endDate) : $endDate;
        $this->onDate = is_int($onDate) ? Carbon::createFromTimestamp($onDate) : $onDate;
    }

    public function getStartDate(): ?Carbon
    {
        return $this->startDate;
    }

    public function getEndDate(): ?Carbon
    {
        return $this->endDate;
    }

    public function getOnDate(): ?Carbon
    {
        return $this->onDate;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function isRoundToDay(): bool
    {
        return $this->roundToDay;
    }

    public function isPqlFieldNameResolutionEnabled(): bool
    {
        return $this->enablePqlFieldNameResolution;
    }

}