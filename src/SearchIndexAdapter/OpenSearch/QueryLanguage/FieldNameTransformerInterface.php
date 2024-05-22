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
    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string;

    /**
     * Stops the propagation of the field name transformation if the current transformer was applied.
     * If the transformation is stopped, the next transformer will not be called.
     */
    public function stopPropagation(): bool;
}
