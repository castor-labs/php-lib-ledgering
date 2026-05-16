<?php

declare(strict_types=1);

namespace Castor\Symfony\Command;

use Castor\Ledgering\ExpirePendingTransfers as ExpirePendingTransfersCommand;
use Castor\Ledgering\Ledger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to expire pending transfers that have exceeded their timeout.
 *
 * This command is designed to be run periodically via cron to automatically
 * void pending transfers that have timed out.
 *
 * Example cron entry (run every minute):
 *   * * * * * php bin/console castor:ledgering:expire-pending-transfers
 *
 * Example cron entry (run every 5 minutes):
 *   *\/5 * * * * php bin/console castor:ledgering:expire-pending-transfers
 */
#[AsCommand(
	name: 'castor:ledgering:expire-pending-transfers',
	description: 'Expire pending transfers that have exceeded their timeout',
)]
final class ExpirePendingTransfers extends Command
{
	public function __construct(
		private readonly Ledger $ledger,
	) {
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$io->title('Expiring Pending Transfers');

		try {
			// Execute the expire command using the current time
			$this->ledger->execute(ExpirePendingTransfersCommand::now());

			$io->success('Successfully expired pending transfers');

			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$io->error(\sprintf('Failed to expire pending transfers: %s', $e->getMessage()));

			if ($output->isVerbose()) {
				$io->block($e->getTraceAsString(), 'TRACE', 'fg=red', ' ', true);
			}

			return Command::FAILURE;
		}
	}
}
