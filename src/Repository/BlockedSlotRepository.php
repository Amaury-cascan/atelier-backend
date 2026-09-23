<?php

namespace App\Repository;

use App\Entity\BlockedSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedSlot>
 */
class BlockedSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedSlot::class);
    }

    /**
     * Créneaux bloqués qui chevauchent [from, to[.
     *
     * @return BlockedSlot[]
     */
    public function findOverlapping(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.startAt < :to')
            ->andWhere('b.endAt > :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('b.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return BlockedSlot[]
     */
    public function findFrom(\DateTimeInterface $from): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.endAt >= :from')
            ->setParameter('from', $from)
            ->orderBy('b.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
