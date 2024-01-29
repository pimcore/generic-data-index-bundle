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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Exception;

interface IndexHandlerInterface
{
    /**
     * @throws Exception
     */
    public function updateMapping(mixed $context = null, bool $forceCreateIndex = false): void;

    public function deleteIndex(mixed $context): void;
}