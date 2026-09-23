<?php

namespace App\Repository;

use App\Entity\ScheduleVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduleVersion>
 */
class ScheduleVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduleVersion::class);
    }

    /**
     * Version applicable à une date : effectiveFrom la plus récente ≤ date.
     */
    public function findApplicableOn(\DateTimeInterface $date): ?ScheduleVersion
    {
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return $this->createQueryBuilder('v')
            ->leftJoin('v.openings', 'o')
            ->addSelect('o')
            ->andWhere('v.effectiveFrom <= :day')
            ->setParameter('day', $day)
            ->orderBy('v.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ScheduleVersion[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.openings', 'o')
            ->addSelect('o')
            ->orderBy('v.effectiveFrom', 'ASC')
            ->addOrderBy('o.weekday', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
