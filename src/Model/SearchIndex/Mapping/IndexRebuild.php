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

/**
 * One index whose elements were queued for a rebuild, with the reason.
 *
 * @internal
 */
final readonly class IndexRebuild
{
    /**
     * @param string $label class name, "assets" or "documents"
     */
    public function __construct(
        private string $label,
        private MappingUpdateResult $result,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getResult(): MappingUpdateResult
    {
        return $this->result;
    }
}
