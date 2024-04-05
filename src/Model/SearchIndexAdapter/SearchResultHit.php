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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

readonly class SearchResultHit
{
    public function __construct(
        private string $id,
        private string $index,
        private ?float $score,
        private array $source,
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
}
