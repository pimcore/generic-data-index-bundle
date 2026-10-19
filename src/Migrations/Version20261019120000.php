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

final class Version20261019120000 extends AbstractMigration
{
    private const INDEX_NAME = IndexQueue::TABLE . '_element_index_name';

    public function getDescription(): string
    {
        return 'Add index on elementIndexName column in generic_data_index_queue table (index status counts per index)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable(IndexQueue::TABLE)->hasIndex(self::INDEX_NAME)) {
            $this->addSql('ALTER TABLE `' . IndexQueue::TABLE . '`
                ADD INDEX `' . self::INDEX_NAME . '` (`elementIndexName`)
            ;');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable(IndexQueue::TABLE)->hasIndex(self::INDEX_NAME)) {
            $this->addSql('ALTER TABLE `' . IndexQueue::TABLE . '`
                DROP INDEX `' . self::INDEX_NAME . '`
            ;');
        }
    }
}
