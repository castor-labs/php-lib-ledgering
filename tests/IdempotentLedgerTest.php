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

namespace Castor\Ledgering;

use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdempotentLedgerTest extends TestCase
{
	private AccountCollection $accounts;

	private TransferCollection $transfers;

	private AccountBalanceCollection $accountBalances;

	private StandardLedger $standardLedger;

	private IdempotentLedger $ledger;

	#[\Override]
	protected function setUp(): void
	{
		$this->standardLedger = new StandardLedger(
			$this->accounts = new AccountCollection(),
			$this->transfers = new TransferCollection(),
			$this->accountBalances = new AccountBalanceCollection(),
		);

		$this->ledger = new IdempotentLedger($this->standardLedger);
	}

	#[Test]
	public function it_creates_account_successfully(): void
	{
		$command = CreateAccount::with(
			id: TestIdentifiers::accountOne(),
			ledger: 1,
			code: 100,
		);

		$this->ledger->execute($command);

		$account = $this->accounts->ofId($command->id)->one();
		self::assertTrue($account->id->equals($command->id));
	}

	#[Test]
	public function it_suppresses_duplicate_account_creation(): void
	{
		$command = CreateAccount::with(
			id: TestIdentifiers::accountOne(),
			ledger: 1,
			code: 100,
		);

		// First execution - creates the account
		$this->ledger->execute($command);

		// Second execution - should not throw
		$this->ledger->execute($command);

		// Verify account exists and was only created once
		$account = $this->accounts->ofId($command->id)->one();
		self::assertTrue($account->id->equals($command->id));
	}

	#[Test]
	public function it_suppresses_duplicate_transfer_creation(): void
	{
		// Create accounts first
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		// First execution - creates the transfer
		$this->ledger->execute($command);

		// Second execution - should not throw
		$this->ledger->execute($command);

		// Verify transfer exists and was only created once
		$transfer = $this->transfers->ofId($command->id)->one();
		self::assertTrue($transfer->id->equals($command->id));
		self::assertSame(1000, $transfer->amount->value);
	}

	#[Test]
	public function it_propagates_other_constraint_violations(): void
	{
		// Try to create a transfer without creating accounts first
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::AccountNotFound->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_propagates_balance_constraint_violations(): void
	{
		// Create account with overdraft protection
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
			),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Try to debit from empty account
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::DebitsExceedCredits->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_handles_batched_commands_with_duplicates(): void
	{
		$account1 = CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100);
		$account2 = CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200);

		// First batch - creates both accounts
		$this->ledger->execute($account1, $account2);

		// Second batch - tries to create account1 again (duplicate)
		// Should suppress the duplicate error and not throw
		$this->ledger->execute($account1);

		// Verify both accounts exist
		self::assertNotNull($this->accounts->ofId(TestIdentifiers::accountOne())->first());
		self::assertNotNull($this->accounts->ofId(TestIdentifiers::accountTwo())->first());
	}

	#[Test]
	public function it_handles_multiple_duplicate_attempts(): void
	{
		$command = CreateAccount::with(
			id: TestIdentifiers::accountOne(),
			ledger: 1,
			code: 100,
		);

		// Execute the same command multiple times
		$this->ledger->execute($command);
		$this->ledger->execute($command);
		$this->ledger->execute($command);

		// Should only create one account
		$account = $this->accounts->ofId($command->id)->one();
		self::assertTrue($account->id->equals($command->id));
	}

	#[Test]
	public function it_propagates_ledger_mismatch_errors(): void
	{
		// Create accounts with different ledger codes
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 2, code: 200),
		);

		// Try to transfer between accounts with different ledgers
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::LedgerMismatch->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_propagates_same_debit_and_credit_account_errors(): void
	{
		// Create account
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
		);

		// Try to transfer to the same account
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountOne(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::SameDebitAndCreditAccount->value);

		$this->ledger->execute($command);
	}
}
