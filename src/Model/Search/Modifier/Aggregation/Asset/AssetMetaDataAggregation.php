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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final class AssetMetaDataAggregation implements SearchModifierInterface
{
    private const AGGREGATION_NAME_PREFIX = 'asset_meta_data_';

    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly ?string $language = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAggregationName(): string
    {
        return self::AGGREGATION_NAME_PREFIX . $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }
}
