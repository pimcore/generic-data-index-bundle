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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Source;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Source\SystemFieldsOnlySource;

/**
 * @internal
 */
final readonly class SourceHandlers
{
    #[AsSearchModifierHandler]
    public function handleSystemFieldsOnlySource(
        SystemFieldsOnlySource $systemFieldsOnlySource,
        SearchModifierContextInterface $context
    ): void {
        // Only the data object search result items lazy-load their search index data.
        if (!$context->getOriginalSearch() instanceof DataObjectSearchInterface) {
            return;
        }

        // The inherited field origins are small and not lazy-loaded, so they are fetched with the system fields.
        $context->getSearch()->setSource([
            FieldCategory::SYSTEM_FIELDS->value,
            FieldCategory::STANDARD_FIELDS->value . '.' . FieldCategory::INHERITED_FIELDS->value,
        ]);
    }
}
