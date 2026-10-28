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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Doctrine\DBAL\Connection;
use Exception;
use Pimcore\Bundle\StaticResolverBundle\Models\Tool\SettingsStoreResolverInterface;
use Pimcore\Model\Tool\SettingsStore;

/**
 * @internal
 */
final class SettingsStoreService implements SettingsStoreServiceInterface
{
    private const SETTINGS_STORE_PREFIX = 'reindex_class_id_';

    private const INDEX_STATE_PREFIX = 'index_state_';

    private const SETTINGS_STORE_SCOPE = 'generic_data_index';

    private const SETTINGS_STORE_TABLE = 'settings_store';

    public function __construct(
        private readonly SettingsStoreResolverInterface $settingsStoreResolver,
        private readonly Connection $connection,
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

    public function getIndexState(string $key): ?string
    {
        $data = $this->settingsStoreResolver->get(
            self::INDEX_STATE_PREFIX . $key,
            self::SETTINGS_STORE_SCOPE
        )?->getData();

        return $data === null ? null : (string) $data;
    }

    /**
     * @throws Exception
     */
    public function storeIndexState(string $key, string $value): void
    {
        $this->settingsStoreResolver->set(
            self::INDEX_STATE_PREFIX . $key,
            $value,
            SettingsStore::TYPE_STRING,
            self::SETTINGS_STORE_SCOPE
        );
    }

    public function removeIndexState(string $key): void
    {
        $this->settingsStoreResolver->delete(
            self::INDEX_STATE_PREFIX . $key,
            self::SETTINGS_STORE_SCOPE
        );
    }

    public function replaceIndexState(string $key, string $expected, string $value): bool
    {
        // The settings store has no compare-and-set; one UPDATE with the expected value in its condition is one.
        $affected = $this->connection->executeStatement(
            'UPDATE ' . self::SETTINGS_STORE_TABLE
            . ' SET data = :value WHERE id = :id AND scope = :scope AND data = :expected',
            [
                'value' => $value,
                'id' => self::INDEX_STATE_PREFIX . $key,
                'scope' => self::SETTINGS_STORE_SCOPE,
                'expected' => $expected,
            ]
        );

        return (int) $affected > 0;
    }

    public function removeIndexStateIfEquals(string $key, string $expected): bool
    {
        $affected = $this->connection->executeStatement(
            'DELETE FROM ' . self::SETTINGS_STORE_TABLE . ' WHERE id = :id AND scope = :scope AND data = :expected',
            [
                'id' => self::INDEX_STATE_PREFIX . $key,
                'scope' => self::SETTINGS_STORE_SCOPE,
                'expected' => $expected,
            ]
        );

        return (int) $affected > 0;
    }
}
