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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final class ElementKeySearch implements SearchModifierInterface
{
    public function __construct(
        private readonly ?string $searchTerm,
    ) {
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }
}
