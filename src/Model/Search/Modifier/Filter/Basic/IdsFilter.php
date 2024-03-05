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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\ValueObject\Collection\ArrayOfPositiveIntegers;

final class IdsFilter implements SearchModifierInterface
{

    private ArrayOfPositiveIntegers $ids;

    public function __construct(array $ids = []) {
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
