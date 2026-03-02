<?php

namespace App\Command;

use App\Repository\PlanningEtudeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:planning:auto-skip',
    description: 'Mark expired planning activities as skipped.'
)]
final class PlanningAutoSkipCommand extends Command
{
    public function __construct(
        private PlanningEtudeRepository $planningRepo,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTime();
        $today = new \DateTime($now->format('Y-m-d'));

        $qb = $this->planningRepo->createQueryBuilder('p');
        $activities = $qb
            ->andWhere('p.date_seance <= :today')
            ->andWhere('p.etat IS NULL OR p.etat NOT IN (:done, :skipped)')
            ->andWhere('p.heure_debut IS NOT NULL')
            ->andWhere('p.duree_prevue IS NOT NULL')
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('done', 'done')
            ->setParameter('skipped', 'skipped')
            ->getQuery()
            ->getResult();

        $updated = 0;
        foreach ($activities as $activity) {
            $start = $activity->getHeureDebut();
            $duration = $activity->getDureePrevue();
            if (!$start || !$duration) {
                continue;
            }

            $startDateTime = new \DateTime($activity->getDateSeance()->format('Y-m-d') . ' ' . $start->format('H:i'));
            $endDateTime = (clone $startDateTime)->modify(sprintf('+%d minutes', $duration));

            if ($now > $endDateTime) {
                $activity->setEtat('skipped');
                $activity->setDateModification(new \DateTime());
                $updated += 1;
            }
        }

        if ($updated > 0) {
            $this->em->flush();
        }

        $output->writeln(sprintf('Updated %d activities.', $updated));

        return Command::SUCCESS;
    }
}
