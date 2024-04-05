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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;

readonly class SearchModifierContext implements SearchModifierContextInterface
{
    public function __construct(
        private OpenSearchSearchInterface $search,
    ) {
    }

    public function getSearch(): OpenSearchSearchInterface
    {
        return $this->search;
    }
}
