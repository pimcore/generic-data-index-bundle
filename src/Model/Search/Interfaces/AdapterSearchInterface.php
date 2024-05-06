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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSortList;

interface AdapterSearchInterface
{
    public function getFrom(): ?int;

    public function setFrom(?int $from): AdapterSearchInterface;

    public function getSize(): ?int;

    public function setSize(?int $size): AdapterSearchInterface;

    public function getSortList(): FieldSortList;

    public function setSortList(FieldSortList $sortList): OpenSearchSearchInterface;

    public function isReverseItemOrder(): bool;

    public function setReverseItemOrder(bool $reverseItemOrder): OpenSearchSearchInterface;

    public function toArray(): array;
}
