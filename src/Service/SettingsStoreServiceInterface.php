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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Exception;

/**
 * @internal
 */
interface SettingsStoreServiceInterface
{
    public function getClassMappingCheckSum(
        string $classDefinitionId
    ): ?int;

    /**
     * @throws Exception
     */
    public function storeClassMapping(
        string $classDefinitionId,
        int $data
    ): void;

    public function removeClassMapping(
        string $classDefinitionId
    ): void;
}
