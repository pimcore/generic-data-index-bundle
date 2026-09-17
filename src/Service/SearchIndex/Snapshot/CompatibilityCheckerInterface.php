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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\CompatibilityReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;

/**
 * @internal
 */
interface CompatibilityCheckerInterface
{
    public function check(Manifest $manifest): CompatibilityReport;
}
