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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class UpdateLanguageSettingsHandler
{
    use LoggerAwareTrait;

    /** Index state key: the dispatch time of the last language change that was applied to the indices. */
    private const APPLIED_CHANGE_KEY = 'language_change_applied_at';

    public function __construct(
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly LanguageServiceInterface $languageService,
        private readonly ReindexServiceInterface $reindexService,
        private readonly MappingUpdateRetryPolicyInterface $retryPolicy,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
    ) {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
    }

    /**
     * A failed update is reported to Messenger instead of being logged only: the message is retried, or goes to
     * the failed transport when a copy ran out of its budget, and the retry updates every index again. A retried
     * message that is older than a language change applied meanwhile is skipped: it would reinstall the language
     * list of its save, which a later save has replaced.
     *
     * @throws Exception
     */
    public function __invoke(UpdateLanguageSettingsMessage $message): void
    {
        if ($this->isSuperseded($message)) {
            $this->logger->notice('Skipping a language change that a later one has replaced', [
                'languages' => $message->getValidLanguages(),
            ]);

            return;
        }

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

        $this->rememberAppliedChange($message);
    }

    private function isSuperseded(UpdateLanguageSettingsMessage $message): bool
    {
        $dispatchedAt = $message->getDispatchedAt();
        if ($dispatchedAt === null) {
            return false;
        }

        $appliedAt = $this->settingsStoreService->getIndexState(self::APPLIED_CHANGE_KEY);

        return $appliedAt !== null && (int) $appliedAt > $dispatchedAt;
    }

    private function rememberAppliedChange(UpdateLanguageSettingsMessage $message): void
    {
        $dispatchedAt = $message->getDispatchedAt();
        if ($dispatchedAt !== null) {
            $this->settingsStoreService->storeIndexState(self::APPLIED_CHANGE_KEY, (string) $dispatchedAt);
        }
    }
}
