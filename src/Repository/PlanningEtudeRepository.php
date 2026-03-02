<?php

namespace App\Repository;

use App\Entity\PlanningEtude;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanningEtude>
 */
class PlanningEtudeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanningEtude::class);
    }

    /**
     * @return PlanningEtude[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.date_seance >= :start')
            ->andWhere('p.date_seance <= :end')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->orderBy('p.date_seance', 'ASC')
            ->addOrderBy('p.heure_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{type: string, color: ?string}>
     */
    public function findDistinctTypesWithColor(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.type_activite AS type', 'MAX(p.couleur_activite) AS color')
            ->andWhere('p.type_activite IS NOT NULL')
            ->groupBy('p.type_activite')
            ->orderBy('p.type_activite', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findColorForType(string $type): ?string
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.couleur_activite AS color')
            ->andWhere('p.type_activite = :type')
            ->setParameter('type', $type)
            ->andWhere('p.couleur_activite IS NOT NULL')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['color'] ?? null;
    }

    public function findLastByTitle(string $title): ?PlanningEtude
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.titre_p) = :title')
            ->setParameter('title', mb_strtolower($title))
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PlanningEtude[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.date_seance = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('p.heure_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return PlanningEtude[] Returns an array of PlanningEtude objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?PlanningEtude
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
