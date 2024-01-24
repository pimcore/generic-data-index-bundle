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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Model\Asset;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires before the data for assets gets updated in the search index.
 * Can be used to add additional customized attributes in the search index.
 * You will find a description and example on how it works in the docs.
 */
final class UpdateIndexDataEvent extends Event implements UpdateIndexDataEventInterface
{
    protected Asset $asset;

    protected array $customFields;

    public function __construct(Asset $asset, array $customFields)
    {
        $this->asset = $asset;
        $this->customFields = $customFields;
    }

    public function getElement(): Asset
    {
        return $this->asset;
    }

    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function setCustomFields(array $customFields): self
    {
        $this->customFields = $customFields;

        return $this;
    }
}
