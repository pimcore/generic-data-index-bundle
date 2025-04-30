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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Helper;

use Codeception\Test\Unit;

/**
 * @internal
 */
final class WorkspaceHelper extends Unit
{
    public static function create(): self
    {
        return new self('MockupData');
    }

    public function getUserWorkspace(string $type, string $path): mixed
    {
        $workspace = new $type();
        $workspace->setCpath($path);

        return $workspace;
    }
}
