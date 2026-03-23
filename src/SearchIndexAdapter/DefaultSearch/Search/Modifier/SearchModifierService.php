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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

/**
 * @internal
 */
final class SearchModifierService implements SearchModifierServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly CachedSearchIndexMappingServiceInterface $cachedSearchIndexMappingService
    ) {
    }

    /**
     * @var callable[][]
     */
    private array $searchModifierHandlers = [];

    public function addSearchModifierHandler(
        string $modifierClass,
        object $searchModifierHandlerClass,
        string $method
    ): void {
        $this->searchModifierHandlers[$modifierClass] = $this->searchModifierHandlers[$modifierClass] ?? [];
        $this->searchModifierHandlers[$modifierClass][] = [
            'class' => $searchModifierHandlerClass,
            'method' => $method,
        ];
    }

    public function applyModifier(
        SearchModifierInterface $modifier,
        SearchModifierContextInterface $modifierContext
    ): void {
        /** @var string $modifierClass */
        foreach ($this->searchModifierHandlers as $modifierClass => $handlers) {
            if ($modifier instanceof $modifierClass) {
                foreach ($handlers as $handler) {

                    $this->logger->info(sprintf(
                        'Applying search modifier %s with handler %s::%s',
                        $modifierClass,
                        get_class($handler['class']),
                        $handler['method']
                    ));

                    $handler['class']->{$handler['method']}($modifier, $modifierContext);
                }
            }
        }
    }

    /**
     * @param Search $adapterSearch
     */
    public function applyModifiersFromSearch(
        SearchInterface $search,
        AdapterSearchInterface $adapterSearch
    ): void {

        $cachingWasAlreadyStarted = $this->cachedSearchIndexMappingService->isCachingStarted();
        $this->cachedSearchIndexMappingService->startCaching();

        $this->doApplyModifiersFromSearch($search, $adapterSearch);

        if (!$cachingWasAlreadyStarted) {
            $this->cachedSearchIndexMappingService->stopCaching();
        }
    }

    private function doApplyModifiersFromSearch(
        SearchInterface $search,
        Search $adapterSearch
    ): void {
        $context = new SearchModifierContext($adapterSearch, $search);
        foreach ($search->getModifiers() as $modifier) {
            $this->applyModifier($modifier, $context);
        }
    }
}
