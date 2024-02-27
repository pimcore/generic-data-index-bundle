<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\MetadataMappingProvider;

interface MappingProviderInterface
{
    public function addMapping(array $mapping): array;
}