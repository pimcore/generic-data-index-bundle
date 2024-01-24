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

use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires before the mapping will be sent to the search server index.
 * Can be used to add mappings for customized additional fields.
 * You will find a description and example on how it works in the docs.
 */
final class ExtractMappingEvent extends Event
{
    protected ClassDefinition $classDefinition;

    protected array $customFieldsMapping;

    public function __construct(ClassDefinition $classDefinition, array $customFieldsMapping)
    {
        $this->classDefinition = $classDefinition;
        $this->customFieldsMapping = $customFieldsMapping;
    }

    public function getClassDefinition(): ClassDefinition
    {
        return $this->classDefinition;
    }

    public function getCustomFieldsMapping(): array
    {
        return $this->customFieldsMapping;
    }

    public function setCustomFieldsMapping(array $customFieldsMapping): self
    {
        $this->customFieldsMapping = $customFieldsMapping;

        return $this;
    }
}
