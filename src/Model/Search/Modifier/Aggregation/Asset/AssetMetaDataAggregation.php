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
