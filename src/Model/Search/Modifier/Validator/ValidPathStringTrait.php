<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validator;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;

/**
 * @internal
 */
trait ValidPathStringTrait
{
    private function validatePathString(string $path): void
    {
        if (!str_starts_with($path, '/')) {
            throw new InvalidModifierException('Path must start with a slash.');
        }

        if (str_contains($path, '//')) {
            throw new InvalidModifierException('Path must not contain consecutive slashes.');
        }
    }
}