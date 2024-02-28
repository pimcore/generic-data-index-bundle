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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

interface PaginatedSearchInterface extends SearchInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public function getPage(): int;

    public function setPage(int $page): void;

    public function getPageSize(): int;

    public function setPageSize(int $pageSize): void;
}
