<?php

namespace App\Repository;

use App\Entity\Humeur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Humeur>
 */
class HumeurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Humeur::class);
    }

    /**
     * Get today's mood entry for a specific profile
     */
    public function getTodayMood($profilApprentissage): ?Humeur
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->createQueryBuilder('h')
            ->andWhere('h.profilApprentissage = :profil')
            ->andWhere('h.creeLe >= :today')
            ->andWhere('h.creeLe < :tomorrow')
            ->setParameter('profil', $profilApprentissage)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get mood entries for the last N days
     * @return Humeur[]
     */
    public function getLastNDaysMoods($profilApprentissage, int $days): array
    {
        $startDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('h')
            ->andWhere('h.profilApprentissage = :profil')
            ->andWhere('h.creeLe >= :startDate')
            ->setParameter('profil', $profilApprentissage)
            ->setParameter('startDate', $startDate)
            ->orderBy('h.creeLe', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate average mood for the last N days
     */
    public function getAverageMood($profilApprentissage, int $days): ?int
    {
        $moods = $this->getLastNDaysMoods($profilApprentissage, $days);
        
        if (empty($moods)) {
            return null;
        }

        $sum = array_reduce($moods, function($carry, $humeur) {
            return $carry + $humeur->getValeurHumeur();
        }, 0);

        return (int) round($sum / count($moods));
    }

    /**
     * Calculate the longest streak of consecutive days with mood entries
     */
    public function getLongestStreak($profilApprentissage): int
    {
        $moods = $this->createQueryBuilder('h')
            ->andWhere('h.profilApprentissage = :profil')
            ->setParameter('profil', $profilApprentissage)
            ->orderBy('h.creeLe', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($moods)) {
            return 0;
        }

        $maxStreak = 1;
        $currentStreak = 1;
        $previousDate = null;

        foreach ($moods as $humeur) {
            if ($previousDate === null) {
                $previousDate = $humeur->getCreeLe();
                continue;
            }

            $daysDiff = $previousDate->diff($humeur->getCreeLe())->days;

            if ($daysDiff === 1) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }

            $previousDate = $humeur->getCreeLe();
        }

        return $maxStreak;
    }
}
