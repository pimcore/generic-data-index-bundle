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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Model\User;

interface DocumentSearchServiceInterface
{
    /**
     * @throws Exception
     */
    public function search(SearchInterface $documentSearch): DocumentSearchResult;

    /**
     * @throws Exception
     */
    public function byId(int $id, ?User $user = null): ?DocumentSearchResultItem;
}
