<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */


namespace Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires before the data for data objects gets updated in the search server index.
 * Can be used to add additional customized attributes in the search index.
 * You will find a description and example on how it works in the docs.
 */
final class UpdateIndexDataEvent extends Event implements UpdateIndexDataEventInterface
{
    protected Concrete $dataObject;

    protected array $customFields;

    public function __construct(Concrete $dataObject, array $customFields)
    {
        $this->dataObject = $dataObject;
        $this->customFields = $customFields;
    }

    public function getElement(): Concrete
    {
        return $this->dataObject;
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
