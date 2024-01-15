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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Model\Element\ElementInterface;

interface IndexServiceInterface
{
    public function getCurrentIndexFullPath(ElementInterface $element, string $indexName): ?string;

    public function rewriteChildrenIndexPaths(ElementInterface $element, string $indexName, string $oldFullPath);
}
