<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Clientes dont le dernier rendez-vous est antérieur au seuil.
     * Les comptes sans aucun rendez-vous sont exclus (impossible de dater l'inactivité).
     *
     * @return Client[]
     */
    public function findWithLastAppointmentBefore(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id != :fallbackId')
            ->andWhere('c.id IN (
                SELECT IDENTITY(a.client)
                FROM App\Entity\Appointment a
                GROUP BY a.client
                HAVING MAX(a.date) < :threshold
            )')
            ->setParameter('fallbackId', 1)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Client[] Returns an array of Client objects
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

//    public function findOneBySomeField($value): ?Client
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
