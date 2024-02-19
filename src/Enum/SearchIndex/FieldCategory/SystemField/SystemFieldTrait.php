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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

trait SystemFieldTrait
{
    public function getPath(string $subField = null): string
    {
        $path = FieldCategory::SYSTEM_FIELDS->value . '.' . $this->value;

        if($subField) {
            $path .= '.' . $subField;
        }

        return $path;
    }

    public function getData(array $searchResultHit): mixed
    {
        return $searchResultHit[FieldCategory::SYSTEM_FIELDS->value][$this->value] ?? null;
    }
}
