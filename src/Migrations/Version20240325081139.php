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
