<?php

namespace App\Repository\Compte;

use App\Entity\Compte\Exercice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercice>
 */
class ExerciceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercice::class);
    }

    /**
     * Trouve un exercice par mois et année
     * @param int $year Année
     * @param int $month Mois (1-12)
     * @return Exercice|null
     */
    public function findOneByMonthAndYear(int $year, int $month): ?Exercice
    {
        // Calculer le premier jour du mois
        $startDate = new \DateTime(sprintf('%d-%02d-01 00:00:00', $year, $month));
        // Calculer le dernier jour du mois
        $endDate = new \DateTime(sprintf('%d-%02d-%02d 23:59:59', $year, $month, (int) $startDate->format('t')));
        
        return $this->createQueryBuilder('e')
            ->where('e.mois >= :startDate')
            ->andWhere('e.mois <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Trouve le dernier exercice (par date de mois décroissante)
     * @return Exercice|null
     */
    public function findLastExercice(): ?Exercice
    {
        return $this->createQueryBuilder('e')
            ->where('e.mois IS NOT NULL')
            ->orderBy('e.mois', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
