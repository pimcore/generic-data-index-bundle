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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class OrderByField implements SearchModifierInterface
{
    public function __construct(
        private string $fieldName,
        private SortDirection $direction = SortDirection::ASC,
        private bool $enablePqlFieldNameResolution = true,
    ) {
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getDirection(): SortDirection
    {
        return $this->direction;
    }

    public function isPqlFieldNameResolutionEnabled(): bool
    {
        return $this->enablePqlFieldNameResolution;
    }
}
