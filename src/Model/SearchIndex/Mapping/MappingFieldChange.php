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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingFieldChangeAction;

/**
 * One field whose mapping differs between the live index and the new mapping.
 *
 * @internal
 */
final readonly class MappingFieldChange
{
    /**
     * @param string $path dotted path of the field in the documents, e.g. "standard_fields.l_text.en"
     * @param string $oldShape mapping type in the live index ("object" / "nested" for containers)
     * @param string $newShape mapping type in the new mapping
     */
    public function __construct(
        private string $path,
        private string $oldShape,
        private string $newShape,
        private MappingFieldChangeAction $action,
    ) {
        if ($path === '') {
            throw new InvalidArgumentException('A mapping field change needs a path.');
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOldShape(): string
    {
        return $this->oldShape;
    }

    public function getNewShape(): string
    {
        return $this->newShape;
    }

    public function getAction(): MappingFieldChangeAction
    {
        return $this->action;
    }
}
