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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition;

use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;

/**
 * Checksum as it was calculated before the mapping properties were normalized,
 * i.e. depending on the array key order. Only used to recognise entries stored
 * by an older bundle version; can be dropped once upgrades from those versions
 * are no longer supported.
 *
 * @internal
 */
final class LegacyClassMappingCheckSum
{
    /**
     * @throws JsonException
     */
    public static function calculate(array $mappingProperties): int
    {
        // Like the current checksum, ignore workflowPlaces: it does not depend on the class definition.
        unset(
            $mappingProperties[FieldCategory::SYSTEM_FIELDS->value]['properties'][SystemField::WORKFLOW_PLACES->value]
        );

        return crc32(json_encode($mappingProperties, JSON_THROW_ON_ERROR));
    }
}
