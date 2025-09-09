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

final class Version20251009110653 extends AbstractMigration
{
    private string $indexName = IndexQueue::TABLE . '_element_id_type';

    public function getDescription(): string
    {
        return 'Adapt index queue table to reduce deadlocks and improve performance';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->hasTable(IndexQueue::TABLE)) {
            return;
        }
        $table = $schema->getTable(IndexQueue::TABLE);
        $pk = $table->getPrimaryKey();
        if($pk === null || $pk->getColumns() === ['id']) {
            return;
        }
        
        $table->dropPrimaryKey();
        $table->addColumn('id', 'bigint', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        if(!$table->hasIndex($this->indexName)) {
            $table->addUniqueIndex(['elementId', 'elementType'], $this->indexName);           
        }        
    }

    public function down(Schema $schema): void
    {
        if(!$schema->hasTable(IndexQueue::TABLE)) {
            return;
        }
        $table = $schema->getTable(IndexQueue::TABLE);
        $pk = $table->getPrimaryKey();
        if($pk === null || $pk->getColumns() !== ['id']) {
            return;
        }
        
        $table->dropPrimaryKey();
        $table->dropColumn('id');
        $table->setPrimaryKey(['elementId', 'elementType']);
        if($table->hasIndex($this->indexName)) {
            $table->dropIndex($this->indexName);
        }        
    }
}
