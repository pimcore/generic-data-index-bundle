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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

trait SystemFieldTrait
{
    public function getPath(string $subField = null): string
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
