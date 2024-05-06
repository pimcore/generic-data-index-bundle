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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer;

use Pimcore\Bundle\GenericDataIndexBundle\Service\DocumentTypeSerializationHandler\HandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class DocumentTypeSerializationHandlerService
{
    public function __construct(
        private ServiceLocator $adapterLocator
    ) {
    }

    public function getSerializationHandler(string $type): ?HandlerInterface
    {
        if ($this->adapterLocator->has($type)) {
            try {
                return $this->adapterLocator->get($type);
            } catch (ContainerExceptionInterface) {
                return null;
            }
        }

        return null;
    }
}
