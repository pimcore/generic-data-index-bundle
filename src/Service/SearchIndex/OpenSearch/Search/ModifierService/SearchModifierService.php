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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\ModifierService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\SearchService\SearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

/**
 * @internal
 */
final class SearchModifierService implements SearchModifierServiceInterface
{
    use LoggerAwareTrait;

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

    public function applyModifiersFromSearch(
        SearchServiceInterface $searchService,
        SearchInterface $search,
        Search $openSearchSearch
    ): void {
        $context = new SearchModifierContext($openSearchSearch, $searchService);

        foreach ($search->getModifiers() as $modifier) {
            $this->applyModifier($modifier, $context);
        }
    }
}
