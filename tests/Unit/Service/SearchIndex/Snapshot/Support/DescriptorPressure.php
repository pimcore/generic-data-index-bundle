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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot\Support;

/**
 * Holds open descriptors so that the next one this process is given is numbered at or above the
 * FD_SETSIZE stream_select() is limited to - the state a long-running process reaches on its own.
 *
 * @internal
 */
final class DescriptorPressure
{
    private const SELECT_LIMIT = 1024;

    private const HEADROOM = 48;

    /** @var list<resource> */
    private array $open = [];

    /** @return bool whether the limit could be passed; the process may not be allowed to */
    public function raiseAboveSelectLimit(): bool
    {
        $file = tempnam(sys_get_temp_dir(), 'gdi-fd-');
        if ($file === false) {
            return false;
        }
        for ($i = 0; $i < self::SELECT_LIMIT + self::HEADROOM; $i++) {
            $handle = @fopen($file, 'rb');
            if ($handle === false) {
                break;
            }
            $this->open[] = $handle;
        }
        @unlink($file);

        return count($this->open) >= self::SELECT_LIMIT;
    }

    public function release(): void
    {
        foreach ($this->open as $handle) {
            fclose($handle);
        }
        $this->open = [];
    }
}
