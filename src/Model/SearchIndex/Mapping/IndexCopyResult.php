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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;

/**
 * Result of copying an index into the other -odd/-even index with a new mapping.
 *
 * @internal
 */
final readonly class IndexCopyResult
{
    /**
     * @param string[] $strippedPaths fields removed in the copy (dotted paths)
     * @param string[] $keptPaths changed fields whose old values were kept (dotted paths)
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private ReindexResult $result,
        private array $strippedPaths = [],
        private array $keptPaths = [],
        private int $attempts = 1,
    ) {
        if ($attempts < 1) {
            throw new InvalidArgumentException('A copy result needs at least one attempt.');
        }
    }

    public function getResult(): ReindexResult
    {
        return $this->result;
    }

    public function isSuccessful(): bool
    {
        return $this->result === ReindexResult::SUCCESS;
    }

    /**
     * @return string[]
     */
    public function getStrippedPaths(): array
    {
        return $this->strippedPaths;
    }

    /**
     * @return string[]
     */
    public function getKeptPaths(): array
    {
        return $this->keptPaths;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }
}
