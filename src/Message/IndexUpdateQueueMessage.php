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

namespace Pimcore\Bundle\GenericDataIndexBundle\Message;

class IndexUpdateQueueMessage
{
    public function __construct(protected array $entries)
    {
    }

    public function getEntries(): array
    {
        return $this->entries;
    }
}
