<?php

namespace App\Repository\Compte;

use App\Entity\Compte\DepenseFixe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DepenseFixe>
 */
class DepenseFixeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DepenseFixe::class);
    }

    //    /**
    //     * @return DepenseFixe[] Returns an array of DepenseFixe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DepenseFixe
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
