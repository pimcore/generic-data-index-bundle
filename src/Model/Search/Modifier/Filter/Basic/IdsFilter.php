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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validater\HasPositiveIntArrayTrait;

final class IdsFilter implements SearchModifierInterface
{
    use HasPositiveIntArrayTrait;
    public function __construct(
        private readonly array $ids = []
    ) {
        $this->validatePositiveIntArray($this->ids);
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
