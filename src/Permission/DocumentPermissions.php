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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission;

/**
 * @internal
 */
final class DocumentPermissions extends BasePermissions
{
    private bool $save = self::DEFAULT_VALUE;

    private bool $unpublish = self::DEFAULT_VALUE;

    public function isSave(): bool
    {
        return $this->save;
    }

    public function setSave(bool $save): void
    {
        $this->save = $save;
    }

    public function isUnpublish(): bool
    {
        return $this->unpublish;
    }

    public function setUnpublish(bool $unpublish): void
    {
        $this->unpublish = $unpublish;
    }

    public function getClassProperties(array $properties = []): array
    {
        return parent::getClassProperties(get_object_vars($this));
    }
}
