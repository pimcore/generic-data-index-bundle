<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
        private string $targetQuery
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
}
