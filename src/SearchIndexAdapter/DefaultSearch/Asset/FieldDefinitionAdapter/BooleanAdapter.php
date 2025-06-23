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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\ValueObject\Collection\ArrayOfBoolean;

/**
 * @internal
 */
final class BooleanAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'type' => AttributeType::BOOLEAN->value,
        ];
    }

    public function normalize(mixed $value): bool
    {
        return (bool) $value;
    }

    protected function isValidScalar(mixed $value): bool
    {
        return is_bool($value);
    }

    protected function validateArray(array $value): void
    {
        new ArrayOfBoolean($value);
    }
}
