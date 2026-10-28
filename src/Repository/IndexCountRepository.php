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

namespace Pimcore\Bundle\GenericDataIndexBundle\Repository;

use Doctrine\DBAL\Connection;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * @internal
 */
final readonly class IndexCountRepository implements IndexCountRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function countElements(IndexStateTarget $target): int
    {
        return match ($target->getScope()) {
            DeploymentReindexScope::CLASSES => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM objects WHERE classId = :classId',
                ['classId' => (string) $target->getClassDefinition()?->getId()]
            ),
            DeploymentReindexScope::FOLDERS => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM objects WHERE type = :type',
                ['type' => 'folder']
            ),
            DeploymentReindexScope::ASSETS => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM assets'),
            DeploymentReindexScope::DOCUMENTS => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM documents'),
        };
    }

    public function countQueueEntries(?string $elementIndexName = null): int
    {
        if ($elementIndexName === null) {
            return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . IndexQueue::TABLE);
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . IndexQueue::TABLE . ' WHERE elementIndexName = :elementIndexName',
            ['elementIndexName' => $elementIndexName]
        );
    }
}
