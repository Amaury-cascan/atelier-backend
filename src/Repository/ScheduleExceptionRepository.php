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
        return $this->findOneBy(['date' => $this->asParisDate($date)]);
    }

    /**
     * @return ScheduleException[]
     */
    public function findFrom(\DateTimeInterface $from): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.date >= :from')
            ->setParameter('from', $this->asParisDate($from))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ScheduleException[]
     */
    public function findBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->setParameter('from', $this->asParisDate($from))
            ->setParameter('to', $this->asParisDate($to))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Date civile Europe/Paris pour colonnes DATE. */
    private function asParisDate(\DateTimeInterface $date): \DateTimeImmutable
    {
        $paris = \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('Europe/Paris'));

        return new \DateTimeImmutable($paris->format('Y-m-d'));
    }
}
