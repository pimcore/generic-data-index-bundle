<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\Document;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires before the mapping will be sent to the search server index.
 * Can be used to add mappings for customized additional fields.
 * You will find a description and example on how it works in the docs.
 */
final class ExtractMappingEvent extends Event
{
    protected array $customFieldsMapping;

    public function __construct(array $customFieldsMapping)
    {
        $this->customFieldsMapping = $customFieldsMapping;
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
