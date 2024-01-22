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

namespace Pimcore\Bundle\GenericDataIndexBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;

class IndexQueueRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, IndexQueue::class);
    }

    public function dispatchableItemExists(): bool
    {
        try {
            $result = $this->createQueryBuilder('q')
                ->select('q.operationTime')
                ->where('q.dispatched = 0')
                ->getQuery()
                ->setMaxResults(1)
                ->getOneOrNullResult();

            return $result !== null;
        } catch(NonUniqueResultException) {
            return true;
        }
    }
}
