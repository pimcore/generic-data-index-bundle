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

namespace Pimcore\Bundle\GenericDataIndexBundle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Pimcore;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Migrations\Version20240325081139;
use Pimcore\Extension\Bundle\Installer\Exception\InstallationException;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
final class Installer extends Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller
{
    public function __construct(
        private readonly Connection $db,
        BundleInterface $bundle,

    ) {
        parent::__construct($bundle);
    }

    public function getLastMigrationVersionClassName(): ?string
    {
        return Version20240325081139::class;
    }

    /**
     * @throws SchemaException|Exception
     */
    public function install(): void
    {
        $this->installBundle();
        parent::install();
    }

    /**
     * @throws Exception
     */
    public function uninstall(): void
    {
        $this->uninstallBundle();
        parent::uninstall();
    }

    /**
     * @throws SchemaException|Exception
     */
    private function installBundle(): void
    {
        $currentSchema = $this->db->createSchemaManager()->introspectSchema();
        $this->installIndexQueueTable($currentSchema);
        $this->executeDiffSql($currentSchema);
    }

    /**
     * @throws Exception
     */
    private function uninstallBundle(): void
    {
        $schemaManager = $this->db->createSchemaManager();
        $currentSchema = $schemaManager->introspectSchema();
        $this->executeDiffSql($currentSchema);
        $this->removeIndexQueueTable($currentSchema);
    }

    /**
     * @throws SchemaException
     */
    private function installIndexQueueTable(Schema $schema): void
    {
        if (!$schema->hasTable(IndexQueue::TABLE)) {
            $queueTable = $schema->createTable(IndexQueue::TABLE);
            $queueTable->addColumn('elementId', 'integer', ['notnull' => true, 'unsigned' => true]);
            $queueTable->addColumn('elementType', 'string', ['notnull' => true, 'length' => 20]);
            $queueTable->addColumn('elementIndexName', 'string', ['notnull' => true, 'length' => 255]);
            $queueTable->addColumn('operation', 'string', ['notnull' => true, 'length' => 20]);
            $queueTable->addColumn('operationTime', 'bigint', ['notnull' => true, 'unsigned' => true]);
            $queueTable->addColumn('dispatched', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);

            $queueTable->setPrimaryKey(['elementId', 'elementType']);
            $queueTable->addIndex(['dispatched'], IndexQueue::TABLE . '_dispatched');
            $queueTable->addIndex(['operationTime'], IndexQueue::TABLE . '_operation_time');
        }
    }

    /**
     * @throws Exception
     */
    private function removeIndexQueueTable(Schema $schema): void
    {
        if ($schema->hasTable(IndexQueue::TABLE)) {
            $this->db->executeStatement('DROP TABLE ' . IndexQueue::TABLE);
        }
    }

    /**
     * @throws Exception
     */
    private function executeDiffSql(Schema $newSchema): void
    {
        $currentSchema = $this->db->createSchemaManager()->introspectSchema();
        $schemaComparator = new Comparator($this->db->getDatabasePlatform());
        $schemaDiff = $schemaComparator->compareSchemas($currentSchema, $newSchema);
        $dbPlatform = $this->db->getDatabasePlatform();
        if (!$dbPlatform instanceof AbstractPlatform) {
            throw new InstallationException('Could not get database platform.');
        }

        $sqlStatements = $dbPlatform->getAlterSchemaSQL($schemaDiff);

        if (!empty($sqlStatements)) {
            $this->db->executeStatement(implode(';', $sqlStatements));
        }
    }
}
