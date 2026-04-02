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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class ChildFolderAggregation implements SearchModifierInterface
{
    public const string AGGREGATION_NAME = 'child_folders_with_matches';

    private const int DEFAULT_MAX_FOLDERS = 10000;

    private int $childLevel;

    public function __construct(
        private string $parentPath,
        private int $maxFolders = self::DEFAULT_MAX_FOLDERS,
    ) {
        $segments = array_filter(explode('/', $this->parentPath));
        $this->childLevel = count($segments) + 1;
    }

    public function getParentPath(): string
    {
        return $this->parentPath;
    }

    public function getChildLevel(): int
    {
        return $this->childLevel;
    }

    public function getMaxFolders(): int
    {
        return $this->maxFolders;
    }

    public function getAggregationName(): string
    {
        return self::AGGREGATION_NAME;
    }
}
