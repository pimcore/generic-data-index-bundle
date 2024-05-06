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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType;

interface QueryInterface
{
    public function getType(): QueryType|string;

    public function isEmpty(): bool;

    public function getParams(): array;

    public function toArray(bool $withType = false): array;
}
