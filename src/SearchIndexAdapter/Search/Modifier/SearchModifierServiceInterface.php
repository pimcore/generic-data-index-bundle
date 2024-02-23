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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

interface SearchModifierServiceInterface
{
    public function addSearchModifierHandler(
        string $modifierClass,
        object $searchModifierHandlerClass,
        string $method
    ): void;

    public function applyModifiersFromSearch(
        SearchInterface $search,
        AdapterSearchInterface $adapterSearch
    ): void;
}
