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


final class IdFilter implements SearchModifierInterface
{
    public function __construct(
        private readonly int $id = 1
    ) {
        $this->validate();
    }

    public function getId(): int
    {
        return $this->id;
    }

    private function validate(): void
    {
        if ($this->id < 1) {
            throw new InvalidModifierException("ID must be a positive integer.");
        }
    }
}