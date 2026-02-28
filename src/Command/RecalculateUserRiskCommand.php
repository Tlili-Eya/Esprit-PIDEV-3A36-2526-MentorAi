<?php

namespace App\Command;

use App\Service\UserRiskAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recalculate-user-risk',
    description: 'Recalculate trust score and risk level for all users',
)]
final class RecalculateUserRiskCommand extends Command
{
    public function __construct(private readonly UserRiskAnalyzer $analyzer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('AI User Risk Monitoring — Recalculation');

        $io->text('Analysing all users…');
        $counts = $this->analyzer->analyzeAll();

        $io->success('Risk scores updated and saved to database!');
        $io->table(
            ['Risk Level', 'Users'],
            [
                ['<fg=green>✅ LOW</>',    $counts['LOW']],
                ['<fg=yellow>⚠  MEDIUM</>', $counts['MEDIUM']],
                ['<fg=red>🔴 HIGH</>',    $counts['HIGH']],
            ]
        );

        $total = array_sum($counts);
        $io->text(sprintf('Total users processed: <info>%d</info>', $total));

        return Command::SUCCESS;
    }
}
