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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType;

use Carbon\Carbon;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\ValueObject\Collection\ArrayOfStrings;

final readonly class MultiSelectFilter implements SearchModifierInterface
{
    private ArrayOfStrings $values;

    public function __construct(
        private string $field,
        array $values,
        private bool $enablePqlFieldNameResolution = true,
    ) {
        $this->values = new ArrayOfStrings($values);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValues(): array
    {
        return $this->values->getValue();
    }


    public function isPqlFieldNameResolutionEnabled(): bool
    {
        return $this->enablePqlFieldNameResolution;
    }
}
