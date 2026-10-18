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
     * @param string[] $pathSegments path of the field in the documents, e.g. ['standard_fields', 'l_text', 'en']
     * @param string $oldShape mapping type in the live index ("object" / "nested" for containers)
     * @param string $newShape mapping type in the new mapping
     * @param bool $rebuildRequired a kept field whose old values the engine may convert with a loss (float values
     *                              truncated in a long field); only the rebuild from the database writes them exactly
     */
    public function __construct(
        private array $pathSegments,
        private string $oldShape,
        private string $newShape,
        private MappingFieldChangeAction $action,
        private bool $rebuildRequired = false,
    ) {
        if ($pathSegments === [] || in_array('', $pathSegments, true)) {
            throw new InvalidArgumentException('A mapping field change needs a path without empty segments.');
        }
    }

    /**
     * Dotted path for logs and messages, e.g. "standard_fields.l_text.en".
     */
    public function getPath(): string
    {
        return implode('.', $this->pathSegments);
    }

    /**
     * @return string[]
     */
    public function getPathSegments(): array
    {
        return $this->pathSegments;
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

    public function isRebuildRequired(): bool
    {
        return $this->rebuildRequired;
    }
}
