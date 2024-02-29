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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\AdapterInterface;

interface FieldDefinitionServiceInterface
{
    public function getFieldDefinitionAdapter(string $type): ?AdapterInterface;

    public function normalizeValue(string $type, mixed $value): mixed;
}
