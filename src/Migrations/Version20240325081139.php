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
