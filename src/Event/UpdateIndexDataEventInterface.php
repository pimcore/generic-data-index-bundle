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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event;

use Pimcore\Model\Element\ElementInterface;

interface UpdateIndexDataEventInterface
{
    public function getElement(): ElementInterface;

    public function getCustomFields(): array;

    public function setCustomFields(array $customFields): self;
}
