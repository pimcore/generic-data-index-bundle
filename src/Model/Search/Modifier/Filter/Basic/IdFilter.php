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
use Pimcore\ValueObject\Integer\PositiveInteger;

final class IdFilter implements SearchModifierInterface
{
    private PositiveInteger $id;

    public function __construct(int $id = 1)
    {
        $this->id = new PositiveInteger($id);
    }

    public function getId(): int
    {
        return $this->id->getValue();
    }
}
