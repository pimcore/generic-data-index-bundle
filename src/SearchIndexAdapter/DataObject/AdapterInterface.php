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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject;

use Exception;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;

/**
 * @internal
 */
interface AdapterInterface
{
    public function setFieldDefinition(Data $fieldDefinition): self;

    public function getFieldDefinition(): Data;

    public function getIndexMapping(): array;

    public function getIndexAttributeName(): string;

    /**
     * Used to normalize the data for the search index
     */
    public function normalize(mixed $value): mixed;

    /**
     * @throws Exception
     */
    public function getInheritedData(
        Concrete $dataObject,
        int $objectId,
        mixed $value,
        string $key,
        ?string $language = null,
        callable $callback = null
    ): array;
}
