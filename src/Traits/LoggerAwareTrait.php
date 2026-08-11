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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

trait LoggerAwareTrait
{
    protected LoggerInterface|null $logger;

    #[Required]
    public function setLogger(
        // The dedicated "pimcore_generic_data_index" Monolog channel is selected explicitly by
        // service id, NOT by argument name - so the public parameter keeps its original name and
        // named-argument callers are not broken.
        #[Autowire(service: 'monolog.logger.pimcore_generic_data_index')]
        LoggerInterface $pimcoreLogger,
    ): void {
        $this->logger = $pimcoreLogger;
    }
}
