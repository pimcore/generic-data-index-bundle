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

final class IdsFilter implements SearchModifierInterface
{
    public function __construct(
        private readonly array $ids = []
    ) {
        $this->validate();
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    private function validate() : void
    {
        foreach ($this->ids as $id) {
            if (!is_int($id)) {
                throw new InvalidModifierException("Id must be an integer.");
            }

            if ($id <= 0) {
                throw new InvalidModifierException("ID must be a positive integer.");
            }
        }
    }
}
