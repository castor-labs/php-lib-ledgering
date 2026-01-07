<?php

declare(strict_types=1);

/**
 * @project Castor Ledgering
 * @link https://github.com/castor-labs/php-lib-ledgering
 * @package castor/ledgering
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2024-2026 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor\Symfony\Tests\Command;

use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\ExpirePendingTransfers as ExpirePendingTransfersCommand;
use Castor\Ledgering\Ledger;
use Castor\Symfony\Command\ExpirePendingTransfers;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the ExpirePendingTransfers console command.
 */
final class ExpirePendingTransfersTest extends TestCase
{
	#[Test]
	public function it_has_correct_name_and_description(): void
	{
		$ledger = $this->createMock(Ledger::class);
		$command = new ExpirePendingTransfers($ledger);

		self::assertSame('castor:ledgering:expire-pending-transfers', $command->getName());
		self::assertSame('Expire pending transfers that have exceeded their timeout', $command->getDescription());
	}

	#[Test]
	public function it_executes_successfully_when_ledger_succeeds(): void
	{
		// Arrange
		$ledger = $this->createMock(Ledger::class);
		$ledger->expects(self::once())
			->method('execute')
			->with(self::isInstanceOf(ExpirePendingTransfersCommand::class));

		$command = new ExpirePendingTransfers($ledger);
		$tester = new CommandTester($command);

		// Act
		$exitCode = $tester->execute([]);

		// Assert
		self::assertSame(Command::SUCCESS, $exitCode);
		self::assertStringContainsString('Successfully expired pending transfers', $tester->getDisplay());
		self::assertStringContainsString('Expiring Pending Transfers', $tester->getDisplay());
	}

	#[Test]
	public function it_returns_failure_when_ledger_throws_exception(): void
	{
		// Arrange
		$ledger = $this->createMock(Ledger::class);
		$ledger->expects(self::once())
			->method('execute')
			->willThrowException(new \RuntimeException('Database connection failed'));

		$command = new ExpirePendingTransfers($ledger);
		$tester = new CommandTester($command);

		// Act
		$exitCode = $tester->execute([]);

		// Assert
		self::assertSame(Command::FAILURE, $exitCode);
		self::assertStringContainsString('Failed to expire pending transfers', $tester->getDisplay());
		self::assertStringContainsString('Database connection failed', $tester->getDisplay());
	}

	#[Test]
	public function it_shows_stack_trace_in_verbose_mode(): void
	{
		// Arrange
		$ledger = $this->createMock(Ledger::class);
		$ledger->expects(self::once())
			->method('execute')
			->willThrowException(new \RuntimeException('Test error'));

		$command = new ExpirePendingTransfers($ledger);
		$tester = new CommandTester($command);

		// Act
		$exitCode = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

		// Assert
		self::assertSame(Command::FAILURE, $exitCode);
		$output = $tester->getDisplay();
		self::assertStringContainsString('Test error', $output);
		// Stack trace should be visible in verbose mode
		self::assertStringContainsString('TRACE', $output);
	}

	#[Test]
	public function it_does_not_show_stack_trace_in_normal_mode(): void
	{
		// Arrange
		$ledger = $this->createMock(Ledger::class);
		$ledger->expects(self::once())
			->method('execute')
			->willThrowException(new \RuntimeException('Test error'));

		$command = new ExpirePendingTransfers($ledger);
		$tester = new CommandTester($command);

		// Act
		$exitCode = $tester->execute([]);

		// Assert
		self::assertSame(Command::FAILURE, $exitCode);
		$output = $tester->getDisplay();
		self::assertStringContainsString('Test error', $output);
		// Stack trace should NOT be visible in normal mode
		self::assertStringNotContainsString('TRACE', $output);
	}

	#[Test]
	public function it_calls_ledger_with_now_command(): void
	{
		// Arrange
		$capturedCommand = null;
		$ledger = $this->createMock(Ledger::class);
		$ledger->expects(self::once())
			->method('execute')
			->willReturnCallback(function (...$commands) use (&$capturedCommand): void {
				$capturedCommand = $commands[0];
			});

		$command = new ExpirePendingTransfers($ledger);
		$tester = new CommandTester($command);

		// Act
		$tester->execute([]);

		// Assert
		self::assertInstanceOf(ExpirePendingTransfersCommand::class, $capturedCommand);
		// The command should use a recent timestamp (within last few seconds)
		self::assertNotNull($capturedCommand->asOf);
	}
}

