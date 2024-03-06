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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Service\DocumentTypeSerializationHandler\HandlerInterface;
use Pimcore\Model\Document;

abstract class AbstractSerializationHandler implements HandlerInterface
{
    public function getAdditionalSystemFields(Document $document): array
    {
        return [];
    }
}
