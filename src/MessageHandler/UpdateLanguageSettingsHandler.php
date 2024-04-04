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

namespace Pimcore\Bundle\GenericDataIndexBundle\MessageHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class UpdateLanguageSettingsHandler
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly IndexUpdateServiceInterface $indexUpdateService,
        private readonly LanguageServiceInterface $languageService,
        private readonly EnqueueServiceInterface $enqueueService
    ) {
    }

    public function __invoke(UpdateLanguageSettingsMessage $message): void
    {
        try {
            $currentLanguages = $message->getCurrentLanguages();
            $newLanguages = $this->languageService->getNewLanguages(
                $message->getValidLanguages()
            );

            if ((empty($currentLanguages) && !empty($newLanguages)) ||
                ($currentLanguages !== $newLanguages)
            ) {
                $this->languageService->setValidLanguages($newLanguages);
                $this->handleIndexUpdate();
            }

        } catch (Exception $exception) {
            $this->logger->error('Updating languages failed: ' . $exception);
        }
    }

    /**
     * @throws Exception
     */
    private function handleIndexUpdate(
    ): void {
        $this->indexUpdateService->updateAll();
        $this->enqueueService->dispatchQueueMessages(true);
    }
}
