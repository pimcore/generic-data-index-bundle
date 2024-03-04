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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Service\AssetTypeSerializationHandler\HandlerInterface;
use Pimcore\Model\Asset;

abstract class AbstractHandler implements HandlerInterface
{
    public function getAdditionalSystemFields(Asset $asset): array
    {
        return [];
    }
}
