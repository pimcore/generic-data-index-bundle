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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage;

/**
 * @internal
 */
final readonly class ParseResultSubQuery
{
    public function __construct(
        private string $subQueryId,
        private string $relationFieldPath,
        private string $targetType,
        private string $targetQuery,
        private int $positionInOriginalQuery,
    ) {
    }

    public function getSubQueryId(): string
    {
        return $this->subQueryId;
    }

    public function getRelationFieldPath(): string
    {
        return $this->relationFieldPath;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetQuery(): string
    {
        return $this->targetQuery;
    }

    public function getPositionInOriginalQuery(): int
    {
        return $this->positionInOriginalQuery;
    }
}
