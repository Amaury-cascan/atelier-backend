<?php

namespace App\Repository;

use App\Entity\ScheduleException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduleException>
 */
class ScheduleExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduleException::class);
    }

    public function findOneByDate(\DateTimeInterface $date): ?ScheduleException
    {
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return $this->findOneBy(['date' => $day]);
    }

    /**
     * @return ScheduleException[]
     */
    public function findFrom(\DateTimeInterface $from): array
    {
        $day = \DateTimeImmutable::createFromInterface($from)->setTime(0, 0);

        return $this->createQueryBuilder('e')
            ->andWhere('e.date >= :from')
            ->setParameter('from', $day)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ScheduleException[]
     */
    public function findBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $fromDay = \DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $toDay = \DateTimeImmutable::createFromInterface($to)->setTime(0, 0);

        return $this->createQueryBuilder('e')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
