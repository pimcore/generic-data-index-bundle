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
