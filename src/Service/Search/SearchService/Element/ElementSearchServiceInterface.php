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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ElementSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\SearchResult\ElementSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Model\User;

interface ElementSearchServiceInterface
{
    /**
     * @throws ElementSearchException
     */
    public function search(
        ElementSearchInterface $elementSearch,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): ElementSearchResult;

    /**
     * @throws ElementSearchException
     */
    public function byId(ElementType $elementType, int $id, ?User $user = null): ?ElementSearchResultItemInterface;
}
