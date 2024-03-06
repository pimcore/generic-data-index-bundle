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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\Document;

use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Model\Document;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires before the data for assets gets updated in the search index.
 * Can be used to add additional customized attributes in the search index.
 * You will find a description and example on how it works in the docs.
 */
final class UpdateIndexDataEvent extends Event implements UpdateIndexDataEventInterface
{
    protected Document $document;

    protected array $customFields;

    public function __construct(Document $document, array $customFields)
    {
        $this->document = $document;
        $this->customFields = $customFields;
    }

    public function getElement(): Document
    {
        return $this->document;
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
