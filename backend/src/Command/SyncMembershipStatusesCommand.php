<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AdhesionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Intended to run daily via cron so memberships flip from EN_COURS/ACTIF to
 * EXPIREE/EXPIRE the day after their `date_fin` passes, without needing a
 * request to trigger it. Example crontab entry:
 *   0 2 * * * php /var/www/backend/bin/console app:memberships:sync-status
 */
#[AsCommand(name: 'app:memberships:sync-status', description: 'Expire memberships whose end date has passed')]
class SyncMembershipStatusesCommand extends Command
{
    public function __construct(private readonly AdhesionService $adhesionService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->adhesionService->syncExpiredStatuses();
        $io->success(sprintf('%d adhésion(s) marquée(s) EXPIREE.', $count));

        return Command::SUCCESS;
    }
}
