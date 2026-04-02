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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class TreePqlFilter implements SearchModifierInterface
{
    public function __construct(
        private string $query,
        /** @var string[] */
        private array $relevantFolderKeys,
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return string[]
     */
    public function getRelevantFolderKeys(): array
    {
        return $this->relevantFolderKeys;
    }
}
