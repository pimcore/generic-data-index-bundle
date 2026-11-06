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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLockInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LatestLanguageChangeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
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

    /** The lock all language updates share, next to the locks of the single indices they copy. */
    private const LANGUAGES_LOCK = 'languages';

    public function __construct(
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly LanguageServiceInterface $languageService,
        private readonly ReindexServiceInterface $reindexService,
        private readonly MappingUpdateRetryPolicyInterface $retryPolicy,
        private readonly LatestLanguageChangeInterface $latestLanguageChange,
        private readonly IndexLockInterface $indexLock,
    ) {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
    }

    /**
     * Language updates never overlap: the whole update runs under a lock shared by all language messages, renewed
     * before every index, so a second message waits until the first one has copied every index. It then checks
     * whether a later settings save has replaced its own and skips in that case: it would reinstall the language
     * list of its save. A failed update is reported to Messenger instead of being logged only: the message is
     * retried, or goes to the failed transport when a copy ran out of its budget.
     *
     * @throws Exception
     */
    public function __invoke(UpdateLanguageSettingsMessage $message): void
    {
        $this->indexLock->run(self::LANGUAGES_LOCK, fn () => $this->update($message));
    }

    /**
     * @throws Exception
     */
    private function update(UpdateLanguageSettingsMessage $message): void
    {
        if (!$this->latestLanguageChange->isLatest($message->getValidLanguages())) {
            $this->logger->notice('Skipping a language change that a later settings save has replaced', [
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
            $this->reindexService->reindexAll(fn () => $this->indexLock->refresh(self::LANGUAGES_LOCK));
            $this->enqueueService->dispatchQueueMessages(true);
        } catch (Exception $exception) {
            $this->retryPolicy->rethrow($exception);
        }
    }
}
