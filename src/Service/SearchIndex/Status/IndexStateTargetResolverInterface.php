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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * Maps the names an index goes by (alias name, index queue name, class name) to its target and back.
 *
 * @internal
 */
interface IndexStateTargetResolverInterface
{
    /**
     * Every class index plus the folder, asset and document index.
     *
     * @return IndexStateTarget[]
     */
    public function all(): array;

    /**
     * The alias name, e.g. "pimcore_asset" or "pimcore_data-object_car".
     */
    public function aliasName(IndexStateTarget $target): string;

    /**
     * The name the index queue uses in elementIndexName: the class name, "asset", "document" or
     * "data-object-folder".
     */
    public function elementIndexName(IndexStateTarget $target): string;

    public function fromElementIndexName(string $elementIndexName): ?IndexStateTarget;

    /**
     * Accepts an alias name, an index queue name or a class name.
     */
    public function fromIndexName(string $indexName): ?IndexStateTarget;
}
