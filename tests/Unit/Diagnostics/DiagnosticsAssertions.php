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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Diagnostics;

use PHPUnit\Framework\Assert;
use Pimcore\Contracts\Diagnostics\CheckResult;

/**
 * Shared assertion of the diagnostic check tests: no value of a result may carry a host, a URL scheme, a
 * credential pattern or a driver exception message.
 *
 * @internal
 */
final class DiagnosticsAssertions
{
    private const array FORBIDDEN_PATTERNS = [
        'URL scheme' => '~[a-z][a-z0-9+.\-]*://~i',
        'user info' => '~[^\s@]+@[^\s@]+~',
        'host and port' => '~[a-z0-9\-]+(\.[a-z0-9\-]+)*:\d{2,5}\b~i',
        'IPv4 address' => '~\b\d{1,3}(\.\d{1,3}){3}\b~',
        'credential word' => '~\b(password|passwd|secret|token|apikey|api_key|authorization|bearer)\b~i',
        'SQLSTATE' => '~SQLSTATE~i',
        'exception text' => '~(access denied|exception|stack trace|connection refused)~i',
    ];

    public static function assertNoHostSchemeOrCredential(CheckResult $result): void
    {
        foreach (self::flatten($result->toArray()) as $path => $value) {
            foreach (self::FORBIDDEN_PATTERNS as $name => $pattern) {
                Assert::assertDoesNotMatchRegularExpression(
                    $pattern,
                    $value,
                    sprintf('The value at "%s" contains a %s.', $path, $name)
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $values = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $values += self::flatten($value, $path);

                continue;
            }

            if ($value !== null) {
                $values[$path] = (string) $value;
            }
        }

        return $values;
    }
}
