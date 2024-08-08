<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception;

use Pimcore\Extension\Bundle\Exception\RuntimeException;

/**
 * @internal
 */
final class ClassDefinitionIndexUpdateFailedException extends RuntimeException implements GenericDataIndexBundleExceptionInterface
{
}
