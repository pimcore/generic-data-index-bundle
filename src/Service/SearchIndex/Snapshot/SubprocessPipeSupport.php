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

/**
 * Answers whether this process can still talk to a worker process over pipes.
 *
 * A pipe to a subprocess is driven by stream_select(), and stream_select() fails outright once a
 * descriptor is numbered at or above the FD_SETSIZE PHP was built with (1024 in every shipped
 * build). Symfony's process pipes report that failure by writing nothing and closing nothing, so
 * the worker waits for input that never arrives and for an end of input that never comes, and the
 * importer waits for an answer: the import hangs with no error. A process that already holds about
 * a thousand descriptors - a long-running worker, or a test suite - reaches that point, and the
 * descriptors a new pipe is given are numbered just above the ones already open.
 *
 * The probe opens a descriptor pair of its own and asks stream_select() about it, which is the same
 * question under the same numbering, and closes it again.
 *
 * @internal
 */
final class SubprocessPipeSupport
{
    public static function pipesAreUsable(): bool
    {
        if (!function_exists('stream_socket_pair') || !defined('STREAM_PF_UNIX')) {
            return true;
        }

        $pair = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) {
            return false;
        }

        $read = [$pair[0]];
        $write = [$pair[1]];
        $except = [];
        $usable = @stream_select($read, $write, $except, 0, 0) !== false;

        fclose($pair[0]);
        fclose($pair[1]);

        return $usable;
    }
}
