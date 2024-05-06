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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
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
