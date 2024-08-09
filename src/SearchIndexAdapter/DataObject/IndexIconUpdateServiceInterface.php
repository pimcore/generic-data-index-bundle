<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject;

/**
 * @internal
 */
interface IndexIconUpdateServiceInterface
{
    public function updateIcon(string $indexName, string $icon): void;
}