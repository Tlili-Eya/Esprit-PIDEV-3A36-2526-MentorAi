<?php

namespace App\Repository;

use App\Entity\Feedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    /**
     * Find feedbacks by state (etatfeedback)
     * @return Feedback[]
     */
    public function findByState(string $state): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.etatfeedback = :state')
            ->setParameter('state', $state)
            ->orderBy('f.datefeedback', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search feedbacks by user with optional search term and sort order
     * @return Feedback[]
     */
    public function searchByUser($user, string $searchTerm = '', string $sortOrder = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.utilisateur = :user')
            ->setParameter('user', $user);

        if (!empty($searchTerm)) {
            $qb->andWhere('f.contenu LIKE :search OR f.typefeedback LIKE :search')
               ->setParameter('search', '%' . $searchTerm . '%');
        }

        $qb->orderBy('f.datefeedback', $sortOrder);

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Feedback[] Returns an array of Feedback objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Feedback
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
