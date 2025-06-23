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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Model\Asset;

/**
 * @internal
 */
interface MetadataProviderServiceInterface
{
    /**
     * @return MappingProperty[]
     */
    public function getMappingProperties(): array;

    /**
     * Only metadata that is provided by any mapping provider will be indexed
     * as otherwise we would get a problem with the mapping if types for the same metadata name vary.
     */
    public function getSearchableMetaDataForAsset(Asset $asset): array;
}
