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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;

/**
 * @internal
 */
interface FieldNameTransformerInterface
{
    /**
     * Returns null if the transformer does not apply to the given field name.
     */
    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string;

    /**
     * Stops the propagation of the field name transformation if the current transformer was applied.
     * If the transformation is stopped, the next transformer will not be called.
     */
    public function stopPropagation(): bool;
}
