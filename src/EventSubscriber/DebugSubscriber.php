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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Pimcore;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Helper\ParameterBagHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
final class DebugSubscriber implements EventSubscriberInterface
{
    private const DEBUG_SEARCH_PARAM = 'debug-search-queries';

    public function __construct(private readonly SearchIndexServiceInterface $searchIndexService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->searchIndexService instanceof DefaultSearchService) {
            return;
        }
        if (!Pimcore::inDebugMode() || empty($event->getRequest()->query->get(self::DEBUG_SEARCH_PARAM))) {
            return;
        }
        $verbosity = ParameterBagHelper::getInt($event->getRequest()->query, self::DEBUG_SEARCH_PARAM);
        $event->setResponse(new JsonResponse($this->getNormalizedSearches($verbosity)));
    }

    private function getNormalizedSearches(int $verbosity): array
    {
        if (!$this->searchIndexService instanceof DefaultSearchService) {
            return [];
        }

        $searches = $this->searchIndexService->getExecutedSearches();

        $searches = array_map(
            static fn (SearchInformation $searchInformation) => $searchInformation->toArray($verbosity),
            $searches
        );

        return [
            'number_of_searches' => count($searches),
            'searches' => $searches,
        ];
    }
}
