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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Event\SystemEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @internal
 */
final class AdminSettingsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexUpdateServiceInterface $indexUpdateService,
        private readonly LanguageServiceInterface $languageService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemEvents::SAVE_ACTION_SYSTEM_SETTINGS => 'updateSearchIndex',
        ];
    }

    /**
     * @throws Exception
     */
    public function updateSearchIndex(GenericEvent $event): void
    {
        $arguments = $event->getArguments();
        $currentLanguages = $arguments['existingValues']['general']['valid_languages'];
        $newLanguages = $this->languageService->getNewLanguages(
            explode(',', $arguments['values']['general.validLanguages'])
        );

        if (empty($currentLanguages) && !empty($newLanguages)) {
            $this->setLanguagesAndUpdate($newLanguages);

            return;
        }

        sort($currentLanguages);
        sort($newLanguages);

        if ($currentLanguages !== $newLanguages) {
            $this->setLanguagesAndUpdate($newLanguages);
        }
    }

    /**
     * @throws Exception
     */
    private function setLanguagesAndUpdate(
        array $newLanguages
    ): void {
        $this->languageService->setValidLanguages($newLanguages);
        $this->indexUpdateService->updateAll();
    }
}
