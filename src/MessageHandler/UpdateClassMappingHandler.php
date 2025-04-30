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
