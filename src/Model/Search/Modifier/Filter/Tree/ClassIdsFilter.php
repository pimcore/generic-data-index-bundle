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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class ClassIdsFilter implements SearchModifierInterface
{
    public function __construct(
        private array $classIds,
        private bool $includeFolders = false,
        private bool $useClassName = false
    ) {
    }

    /**
     * @return string[]
     */
    public function getClassIds(): array
    {
        return $this->classIds;
    }

    public function includeFolders(): bool
    {
        return $this->includeFolders;
    }

    public function useClassName(): bool
    {
        return $this->useClassName;
    }
}
