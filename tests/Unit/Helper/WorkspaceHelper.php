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
