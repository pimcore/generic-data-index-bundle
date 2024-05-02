<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;

/**
 * @internal
 */
interface FieldNameTransformerInterface
{
    /**
     * Returns null if the transformer does not apply to the given field name.
     */
    public function transformFieldName(string $fieldName, IndexEntity $indexEntity, array $indexMapping): ?string;

    /**
     * Stops the propagation of the field name transformation if the current transformer was applied.
     * If the transformation is stopped, the next transformer will not be called.
     */
    public function stopPropagation(): bool;
}
