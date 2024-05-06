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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Exception;

/**
 * @internal
 */
interface IndexHandlerInterface
{
    /**
     * @throws Exception
     */
    public function updateMapping(mixed $context = null, bool $forceCreateIndex = false): void;

    public function deleteIndex(mixed $context): void;

    public function getCurrentFullIndexName(mixed $context = null): string;
}
