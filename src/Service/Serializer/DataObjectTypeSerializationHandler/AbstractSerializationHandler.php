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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DataObjectTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Service\DataObjectTypeSerializationHandler\HandlerInterface;
use Pimcore\Model\DataObject;

abstract class AbstractSerializationHandler implements HandlerInterface
{
    public function getAdditionalSystemFields(DataObject $dataObject): array
    {
        return [];
    }
}
