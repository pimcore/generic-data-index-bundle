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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class FullTextSearch implements SearchModifierInterface
{
    /**
     * @param string[] $fields
     */
    public function __construct(
        private string $searchTerm,
        private string $defaultOperator = 'AND',
        private array $fields = [],
        private ?string $flags = 'PHRASE|WHITESPACE',
    ) {
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function getDefaultOperator(): string
    {
        return $this->defaultOperator;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getFlags(): ?string
    {
        return $this->flags;
    }
}
