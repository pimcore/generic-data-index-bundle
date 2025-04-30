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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

trait SystemFieldTrait
{
    public function getPath(?string $subField = null): string
    {
        $path = FieldCategory::SYSTEM_FIELDS->value . '.' . $this->value;

        if ($subField) {
            $path .= '.' . $subField;
        }

        return $path;
    }

    public function getData(array $searchResultHit): mixed
    {
        return $searchResultHit[FieldCategory::SYSTEM_FIELDS->value][$this->value] ?? null;
    }
}
