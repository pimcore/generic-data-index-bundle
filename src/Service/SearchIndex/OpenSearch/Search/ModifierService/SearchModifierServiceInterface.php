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

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\SearchService\SearchServiceInterface;

interface SearchModifierServiceInterface
{
    public function addSearchModifierHandler(
        string $modifierClass,
        object $searchModifierHandlerClass,
        string $method
    ): void;

    public function applyModifier(
        SearchModifierInterface $modifier,
        SearchModifierContextInterface $modifierContext
    ): void;

    public function applyModifiersFromSearch(
        SearchServiceInterface $searchService,
        SearchInterface $search,
        Search $openSearchSearch
    ): void;
}
