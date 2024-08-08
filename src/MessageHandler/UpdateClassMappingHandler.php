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

namespace Pimcore\Bundle\GenericDataIndexBundle\MessageHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateClassMappingMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class UpdateClassMappingHandler
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(UpdateClassMappingMessage $message): void
    {
        $classDefinition = $message->getClassDefinition();
        $dispatch = $message->isDispatchQueueMessages();

        $changed = $this->classDefinitionReindexService->reindexClassDefinition(
            $classDefinition,
            true,
            $dispatch
        );

        if (!$changed) {
            return;
        }

        if ($dispatch) {
            $this->enqueueService
                ->dispatchQueueMessages();
        }
    }
}
