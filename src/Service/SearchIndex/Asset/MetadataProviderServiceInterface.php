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
