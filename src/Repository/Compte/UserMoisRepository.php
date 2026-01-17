<?php

namespace App\Repository\Compte;

use App\Entity\Compte\UserMois;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMois>
 */
class UserMoisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMois::class);
    }

    //    /**
    //     * @return UserMois[] Returns an array of UserMois objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserMois
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
