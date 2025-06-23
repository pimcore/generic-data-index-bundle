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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\ValueObject\Collection\ArrayOfPositiveIntegers;

final class IdsFilter implements SearchModifierInterface
{
    private ArrayOfPositiveIntegers $ids;

    public function __construct(array $ids = [])
    {
        $this->ids = new ArrayOfPositiveIntegers($ids);
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids->getValue();
    }
}
