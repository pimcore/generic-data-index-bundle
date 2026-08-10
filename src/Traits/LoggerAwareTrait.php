<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Traits;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait LoggerAwareTrait
{
    protected LoggerInterface|null $logger;

    #[Required]
    public function setLogger(LoggerInterface $pimcoreGenericDataIndexLogger): void
    {
        // Bind to the dedicated "pimcore_generic_data_index" Monolog channel (declared in the
        // bundle extension). Autowiring resolves the argument name to that channel's logger.
        $this->logger = $pimcoreGenericDataIndexLogger;
    }
}
