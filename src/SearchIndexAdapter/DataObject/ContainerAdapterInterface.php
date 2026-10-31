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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject;

/**
 * Object brick, field collection or block adapter.
 *
 * @internal
 */
interface ContainerAdapterInterface
{
    /**
     * Turns the value normalize() returned into its index document structure: the attributes of the container's
     * localized fields move up next to its other fields as <attribute>.<language>, as on the class level, also in
     * nested containers. normalize() itself keeps the "localizedfields" key for its other consumers.
     */
    public function flattenLocalizedFields(mixed $normalizedValue): mixed;
}
