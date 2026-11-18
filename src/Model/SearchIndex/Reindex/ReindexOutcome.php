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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;

/**
 * Result of one copy attempt into the other -odd/-even index.
 *
 * @internal
 */
final readonly class ReindexOutcome
{
    /**
     * @param string[][] $failingFieldPaths fields named in the parsing failures of an incompatible copy
     */
    public function __construct(
        private ReindexResult $result,
        private array $failingFieldPaths = [],
    ) {
    }

    public function getResult(): ReindexResult
    {
        return $this->result;
    }

    /**
     * @return string[][]
     */
    public function getFailingFieldPaths(): array
    {
        return $this->failingFieldPaths;
    }
}
