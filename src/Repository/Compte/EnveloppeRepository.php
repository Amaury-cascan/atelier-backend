<?php

namespace App\Repository\Compte;

use App\Entity\Compte\Enveloppe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enveloppe>
 */
class EnveloppeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enveloppe::class);
    }

    //    /**
    //     * @return Enveloppe[] Returns an array of Enveloppe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Enveloppe
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
