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

use Pimcore;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
final class DebugSubscriber implements EventSubscriberInterface
{
    private const DEBUG_SEARCH_PARAM = 'debug-open-search-queries';

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
        if (!$this->searchIndexService instanceof OpenSearchService) {
            return;
        }
        if (!Pimcore::inDebugMode() || empty($event->getRequest()->query->get(self::DEBUG_SEARCH_PARAM))) {
            return;
        }
        $verbosity = $event->getRequest()->query->getInt(self::DEBUG_SEARCH_PARAM);
        $event->setResponse(new JsonResponse($this->getNormalizedSearches($verbosity)));
    }

    private function getNormalizedSearches(int $verbosity): array
    {
        if (!$this->searchIndexService instanceof OpenSearchService) {
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
