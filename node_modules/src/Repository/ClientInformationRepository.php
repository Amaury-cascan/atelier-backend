<?php

namespace App\Repository;


use App\Entity\ClientInformation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientInformation>
 *
 * @method ClientInformation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientInformation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientInformation[]    findAll()
 * @method ClientInformation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientInformationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientInformation::class);
    }

    //    /**
    //     * @return ClientInformation[] Returns an array of ClientInformation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ClientInformation
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
