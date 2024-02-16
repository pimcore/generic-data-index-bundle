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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

use Exception;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface PathServiceInterface
{
    /**
     * Directly update children paths in OpenSearch for assets as otherwise you might get strange results
     * if you rename a folder in the portal engine frontend.
     *
     * @throws Exception
     */
    public function rewriteChildrenIndexPaths(ElementInterface $element): void;

    public function getCurrentIndexFullPath(ElementInterface $element): ?string;
}
