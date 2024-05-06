<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
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

    /**
     * @param Search $adapterSearch
     */
    public function applyModifiersFromSearch(
        SearchInterface $search,
        AdapterSearchInterface $adapterSearch
    ): void {
        $context = new SearchModifierContext($adapterSearch);

        foreach ($search->getModifiers() as $modifier) {
            $this->applyModifier($modifier, $context);
        }
    }
}
