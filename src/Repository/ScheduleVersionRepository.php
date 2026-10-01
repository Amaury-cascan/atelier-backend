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
     *
     * Ne pas combiner leftJoin(openings) + setMaxResults(1) : le LIMIT SQL
     * s'applique alors aux lignes jointes et n'hydrate qu'un seul WeeklyOpening.
     * Les autres jours du calendrier apparaissent à tort comme fermés (OUTSIDE_HOURS).
     */
    public function findApplicableOn(\DateTimeInterface $date): ?ScheduleVersion
    {
        $day = $this->asParisDate($date);

        $version = $this->createQueryBuilder('v')
            ->andWhere('v.effectiveFrom <= :day')
            ->setParameter('day', $day)
            ->orderBy('v.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($version instanceof ScheduleVersion) {
            // Charge les 7 jours (lazy) — requête dédiée, résultat complet.
            $version->getOpenings()->toArray();
        }

        return $version;
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

    /**
     * Date civile Europe/Paris (évite qu'un midnight CEST soit vu comme la veille en UTC).
     */
    private function asParisDate(\DateTimeInterface $date): \DateTimeImmutable
    {
        $paris = \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('Europe/Paris'));

        return new \DateTimeImmutable($paris->format('Y-m-d'));
    }
}
