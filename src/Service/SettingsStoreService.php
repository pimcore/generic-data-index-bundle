<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Exception;
use Pimcore\Bundle\StaticResolverBundle\Models\Tool\SettingsStoreResolverInterface;
use Pimcore\Model\Tool\SettingsStore;

/**
 * @internal
 */
final class SettingsStoreService implements SettingsStoreServiceInterface
{
    private const SETTINGS_STORE_PREFIX = 'reindex_class_id_';

    private const SETTINGS_STORE_SCOPE = 'generic_data_index';

    public function __construct(
        private readonly SettingsStoreResolverInterface $settingsStoreResolver
    ) {
    }

    public function getClassMappingCheckSum(
        string $classDefinitionId
    ): ?int {
        return $this->settingsStoreResolver->get(
            self::SETTINGS_STORE_PREFIX . $classDefinitionId,
            self::SETTINGS_STORE_SCOPE
        )?->getData();
    }

    /**
     * @throws Exception
     */
    public function storeClassMapping(
        string $classDefinitionId,
        int $data
    ): void {

        $this->settingsStoreResolver->set(
            self::SETTINGS_STORE_PREFIX . $classDefinitionId,
            $data,
            SettingsStore::TYPE_INTEGER,
            self::SETTINGS_STORE_SCOPE
        );
    }

    public function removeClassMapping(
        string $classDefinitionId
    ): void {
        $this->settingsStoreResolver->delete(
            self::SETTINGS_STORE_PREFIX . $classDefinitionId,
            self::SETTINGS_STORE_SCOPE
        );
    }
}
