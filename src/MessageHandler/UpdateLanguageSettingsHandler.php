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
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Messenger\MappingUpdateRetryPolicyInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class UpdateLanguageSettingsHandler
{
    public function __construct(
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly LanguageServiceInterface $languageService,
        private readonly ReindexServiceInterface $reindexService,
        private readonly MappingUpdateRetryPolicyInterface $retryPolicy,
    ) {
    }

    /**
     * A failed update is reported to Messenger instead of being logged only: the message is retried, or goes to
     * the failed transport when a copy ran out of its budget, and the retry updates every index again.
     *
     * @throws Exception
     */
    public function __invoke(UpdateLanguageSettingsMessage $message): void
    {
        $currentLanguages = $message->getCurrentLanguages();
        $newLanguages = $this->languageService->getNewLanguages(
            $message->getValidLanguages()
        );

        if ($currentLanguages === $newLanguages) {
            return;
        }

        $this->languageService->setValidLanguages($newLanguages);

        try {
            $this->reindexService->reindexAll();
            $this->enqueueService->dispatchQueueMessages(true);
        } catch (Exception $exception) {
            $this->retryPolicy->rethrow($exception);
        }
    }
}
