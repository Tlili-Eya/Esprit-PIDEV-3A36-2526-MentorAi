<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }
    /**
     * Recherche et tri des utilisateurs
     */
    public function searchAndSort(?string $search = null, ?string $sort = null): array
    {
        $qb = $this->createQueryBuilder('u');

        // Recherche
        if ($search) {
            $qb->where('u.nom LIKE :search')
               ->orWhere('u.prenom LIKE :search')
               ->orWhere('u.email LIKE :search')
               ->orWhere('u.role LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Tri
        switch ($sort) {
            case 'name':
                $qb->orderBy('u.nom', 'ASC')
                   ->addOrderBy('u.prenom', 'ASC');
                break;
            case 'email':
                $qb->orderBy('u.email', 'ASC');
                break;
            case 'role':
                $qb->orderBy('u.role', 'ASC');
                break;
            case 'date':
                $qb->orderBy('u.date_inscription', 'DESC');
                break;
            default:
                $qb->orderBy('u.id', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }


    //    /**
    //     * @return Utilisateur[] Returns an array of Utilisateur objects
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

    //    public function findOneBySomeField($value): ?Utilisateur
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
