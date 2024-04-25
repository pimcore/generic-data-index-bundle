<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage;

/**
 * @internal
 */
interface ProcessorInterface
{
    public function process(string $query): array;
}