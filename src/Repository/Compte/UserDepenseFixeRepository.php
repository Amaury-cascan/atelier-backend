<?php

namespace App\Repository\Compte;

use App\Entity\Compte\UserDepenseFixe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDepenseFixe>
 */
class UserDepenseFixeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDepenseFixe::class);
    }

//    /**
//     * @return UserDepenseFixe[] Returns an array of UserDepenseFixe objects
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

//    public function findOneBySomeField($value): ?UserDepenseFixe
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
