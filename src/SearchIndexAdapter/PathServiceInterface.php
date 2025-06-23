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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

use Exception;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface PathServiceInterface
{
    /**
     * Directly update children paths in search index for assets as otherwise you might get strange results
     * if you rename a folder in the portal engine frontend.
     *
     * @throws Exception
     */
    public function rewriteChildrenIndexPaths(ElementInterface $element): void;

    public function getCurrentIndexFullPath(ElementInterface $element): ?string;
}
