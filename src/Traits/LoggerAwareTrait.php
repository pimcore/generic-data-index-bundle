<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Traits;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait LoggerAwareTrait
{
    protected LoggerInterface|null $logger;

    #[Required]
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
