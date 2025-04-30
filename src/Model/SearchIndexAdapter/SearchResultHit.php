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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

readonly class SearchResultHit
{
    public function __construct(
        private string $id,
        private string $index,
        private ?float $score,
        private array $source,
        private ?array $sort,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function getSource(): array
    {
        return $this->source;
    }

    public function getSort(): ?array
    {
        return $this->sort;
    }
}
