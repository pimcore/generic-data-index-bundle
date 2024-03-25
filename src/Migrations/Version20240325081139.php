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

namespace Pimcore\Bundle\GenericDataIndexBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240325081139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on operationTime column in generic_data_index_queue table';
    }

    public function up(Schema $schema): void
    {
        $indexName = IndexQueue::TABLE . '_operation_time';
        if (!$schema->getTable(IndexQueue::TABLE)->hasIndex($indexName)) {
            $this->addSql('ALTER TABLE `' . IndexQueue::TABLE . '`
                ADD INDEX `' . $indexName . '` (`operationTime`)
            ;');
        }
    }

    public function down(Schema $schema): void
    {
        $indexName = IndexQueue::TABLE . '_operation_time';
        if ($schema->getTable(IndexQueue::TABLE)->hasIndex($indexName)) {
            $this->addSql('ALTER TABLE `' . IndexQueue::TABLE . '`
                DROP INDEX `' . $indexName . '`
            ;');
        }

    }
}
