<?php

declare(strict_types=1);

/**
 * @project Castor Ledgering
 * @link https://github.com/castor-labs/php-lib-ledgering
 * @package castor/ledgering
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2024-2025 CastorLabs Ltd
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

final class StandardLedgerTest extends TestCase
{
	private AccountCollection $accounts;

	private TransferCollection $transfers;

	private AccountBalanceCollection $accountBalances;

	private FixedClock $clock;

	private StandardLedger $ledger;

	#[\Override]
	protected function setUp(): void
	{
		$this->ledger = new StandardLedger(
			$this->accounts = new AccountCollection(),
			$this->transfers = new TransferCollection(),
			$this->accountBalances = new AccountBalanceCollection(),
			$this->clock = FixedClock::at(),
		);
	}

	#[Test]
	public function it_creates_account(): void
	{
		$command = CreateAccount::with(
			id: TestIdentifiers::accountOne(),
			ledger: 1,
			code: 100,
		);

		$this->ledger->execute($command);

		$account = $this->accounts->ofId($command->id)->one();

		self::assertTrue($account->id->equals($command->id));
		self::assertTrue($account->ledger->equals($command->ledger));
		self::assertTrue($account->code->equals($command->code));
		self::assertTrue($account->balance->debitsPosted->isZero());
		self::assertTrue($account->balance->creditsPosted->isZero());
		self::assertTrue($account->balance->debitsPending->isZero());
		self::assertTrue($account->balance->creditsPending->isZero());
	}

	#[Test]
	public function it_prevents_duplicate_account_creation(): void
	{
		$command = CreateAccount::with(
			id: TestIdentifiers::accountOne(),
			ledger: 1,
			code: 100,
		);

		$this->ledger->execute($command);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::AccountAlreadyExists->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_creates_transfer_between_accounts(): void
	{
		// Create accounts
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Create transfer
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->ledger->execute($command);

		$transfer = $this->transfers->ofId($command->id)->one();

		self::assertTrue($transfer->id->equals($command->id));
		self::assertSame(1000, $transfer->amount->value);

		// Check balances
		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		$creditAccount = $this->accounts->ofId(TestIdentifiers::accountTwo())->one();

		self::assertSame(1000, $debitAccount->balance->debitsPosted->value);
		self::assertSame(1000, $creditAccount->balance->creditsPosted->value);
		self::assertSame(0, $debitAccount->balance->debitsPending->value);
		self::assertSame(0, $debitAccount->balance->creditsPending->value);
		self::assertSame(0, $creditAccount->balance->debitsPending->value);
		self::assertSame(0, $creditAccount->balance->creditsPending->value);
	}

	#[Test]
	public function it_makes_transfers_idempotent(): void
	{
		// Create accounts
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Create transfer
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->ledger->execute($command);
		$this->ledger->execute($command); // Same command again

		// Only 1 transfer should exist
		$transfers = $this->transfers->toList();
		self::assertCount(1, $transfers);

		// Balance should only be updated once
		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		self::assertSame(1000, $debitAccount->balance->debitsPosted->value);
	}

	#[Test]
	public function it_rejects_transfer_with_same_debit_and_credit_account(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
		);

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

	#[Test]
	public function it_rejects_transfer_with_zero_amount(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 0,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::ZeroAmount->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_rejects_transfer_when_debit_account_not_found(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 200),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::nonExistent(),
			creditAccountId: TestIdentifiers::accountOne(),
			amount: Amount::of(1000),
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::AccountNotFound->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_rejects_transfer_when_credit_account_not_found(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::nonExistent(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::AccountNotFound->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_rejects_transfer_with_ledger_mismatch(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 2, code: 200),
		);

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

	/**
	 * TODO: We should also test for CreditsExceedDebits constraint.
	 */
	#[Test]
	public function it_enforces_debits_must_not_exceed_credits_constraint(): void
	{
		// Create account with constraint
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
			),
			CreateAccount::with(
				id: TestIdentifiers::accountTwo(),
				ledger: 1,
				code: 200,
			),
		);

		// The first transfer should fail (no credits yet)
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
	public function it_allows_debits_when_credits_exist(): void
	{
		// Create account with constraint
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
			),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Credit the account first
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountTwo(),
				creditAccountId: TestIdentifiers::accountOne(),
				amount: Amount::of(2000),
				ledger: 1,
				code: 1,
			),
		);

		// Now debit should work (up to 2000)
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferTwo(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: Amount::of(1000),
				ledger: 1,
				code: 1,
			),
		);

		$account = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		self::assertSame(2000, $account->balance->creditsPosted->value);
		self::assertSame(1000, $account->balance->debitsPosted->value);
	}

	#[Test]
	public function it_enforces_credits_must_not_exceed_debits_constraint(): void
	{
		// Create account with constraint
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			CreateAccount::with(
				id: TestIdentifiers::accountTwo(),
				ledger: 1,
				code: 200,
			),
		);

		// First transfer should fail (no debits yet)
		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountTwo(),
			creditAccountId: TestIdentifiers::accountOne(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::CreditsExceedDebits->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_rejects_transfers_from_closed_accounts(): void
	{
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::CLOSED,
			),
			CreateAccount::with(
				id: TestIdentifiers::accountTwo(),
				ledger: 1,
				code: 200,
			),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::AccountClosed->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_rejects_transfers_to_closed_accounts(): void
	{
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
			),
			CreateAccount::with(
				id: TestIdentifiers::accountTwo(),
				ledger: 1,
				code: 200,
				flags: AccountFlags::CLOSED,
			),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::AccountClosed->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_records_balance_history_when_flag_is_set(): void
	{
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::HISTORY,
			),
			CreateAccount::with(
				id: TestIdentifiers::accountTwo(),
				ledger: 1,
				code: 200,
			),
		);

		// Make a transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 1000,
				ledger: 1,
				code: 1,
			),
		);

		// Check balance history was recorded
		$history = $this->accountBalances->ofAccountId(TestIdentifiers::accountOne())->toList();
		self::assertCount(1, $history);
		self::assertSame(1000, $history[0]->balance->debitsPosted->value);
	}

	#[Test]
	public function it_creates_pending_transfer(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Create pending transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::pendingOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
			),
		);

		// Check balances - should be in pending, not posted
		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		$creditAccount = $this->accounts->ofId(TestIdentifiers::accountTwo())->one();

		self::assertSame(0, $debitAccount->balance->debitsPosted->value);
		self::assertSame(1000, $debitAccount->balance->debitsPending->value);
		self::assertSame(0, $creditAccount->balance->creditsPosted->value);
		self::assertSame(1000, $creditAccount->balance->creditsPending->value);
	}

	/**
	 * TODO: I think we also need to test that we can post less than the pending amount and the difference from the
	 *  transfer amount is freed from the pending amount. Check if this is Tiger Beetle's behavior.
	 */
	#[Test]
	public function it_posts_pending_transfer(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Create pending transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::pendingOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
			),
		);

		// Post the pending transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 0, // Zero posts the entire pending amount
				ledger: 1,
				code: 1,
				flags: TransferFlags::POST_PENDING,
				pendingId: TestIdentifiers::pendingOne(),
			),
		);

		// Check balances - should be moved from pending to posted
		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		$creditAccount = $this->accounts->ofId(TestIdentifiers::accountTwo())->one();

		self::assertSame(1000, $debitAccount->balance->debitsPosted->value);
		self::assertSame(0, $debitAccount->balance->debitsPending->value);
		self::assertSame(1000, $creditAccount->balance->creditsPosted->value);
		self::assertSame(0, $creditAccount->balance->creditsPending->value);
	}

	#[Test]
	public function it_voids_pending_transfer(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Create pending transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::pendingOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
			),
		);

		// Void the pending transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 0,
				ledger: 1,
				code: 1,
				flags: TransferFlags::VOID_PENDING,
				pendingId: TestIdentifiers::pendingOne(),
			),
		);

		// Check balances - should be back to zero
		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		$creditAccount = $this->accounts->ofId(TestIdentifiers::accountTwo())->one();

		self::assertSame(0, $debitAccount->balance->debitsPosted->value);
		self::assertSame(0, $debitAccount->balance->debitsPending->value);
		self::assertSame(0, $creditAccount->balance->creditsPosted->value);
		self::assertSame(0, $creditAccount->balance->creditsPending->value);
	}

	#[Test]
	public function it_rejects_post_pending_without_pending_id(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 0,
			ledger: 1,
			code: 1,
			flags: TransferFlags::POST_PENDING,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::PendingIdRequired->value);

		$this->ledger->execute($command);
	}

	/**
	 * TODO: We also need to test that when the pending transfer has already been posted or voided, we throw an error.
	 */
	#[Test]
	public function it_rejects_post_pending_when_pending_transfer_not_found(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		$command = CreateTransfer::with(
			id: TestIdentifiers::transferOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: Amount::of(0),
			ledger: 1,
			code: 1,
			flags: TransferFlags::POST_PENDING,
			pendingId: TestIdentifiers::nonExistent(),
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::PendingTransferNotFound->value);

		$this->ledger->execute($command);
	}

	#[Test]
	public function it_calculates_balancing_debit_amount(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Credit the debit account with 1500
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountTwo(),
				creditAccountId: TestIdentifiers::accountOne(),
				amount: 1500,
				ledger: 1,
				code: 1,
			),
		);

		// Balancing debit should calculate amount to zero out the account
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferTwo(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 0,
				ledger: 1,
				code: 1,
				flags: TransferFlags::BALANCING_DEBIT,
			),
		);

		$transferTwo = $this->transfers->ofId(TestIdentifiers::transferTwo())->one();

		// Should have transferred 1500 to balance the account
		self::assertSame(1500, $transferTwo->amount->value);

		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		self::assertSame(1500, $debitAccount->balance->creditsPosted->value);
		self::assertSame(1500, $debitAccount->balance->debitsPosted->value);
	}

	#[Test]
	public function it_calculates_balancing_credit_amount(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Debit the credit account with 2000
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountTwo(),
				creditAccountId: TestIdentifiers::accountOne(),
				amount: 2000,
				ledger: 1,
				code: 1,
			),
		);

		// Balancing credit should calculate amount to zero out the account
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferTwo(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 0,
				ledger: 1,
				code: 1,
				flags: TransferFlags::BALANCING_CREDIT,
			),
		);

		$transfer = $this->transfers->ofId(TestIdentifiers::transferTwo())->one();

		// Should have transferred 2000 to balance the account
		self::assertSame(2000, $transfer->amount->value);

		$creditAccount = $this->accounts->ofId(TestIdentifiers::accountTwo())->one();
		self::assertSame(2000, $creditAccount->balance->debitsPosted->value);
		self::assertSame(2000, $creditAccount->balance->creditsPosted->value);
	}

	#[Test]
	public function it_transfers_entire_balance_with_closing_debit(): void
	{
		$this->ledger->execute(
			CreateAccount::with(id: TestIdentifiers::accountOne(), ledger: 1, code: 100),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Credit the debit account with 3000
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountTwo(),
				creditAccountId: TestIdentifiers::accountOne(),
				amount: 3000,
				ledger: 1,
				code: 1,
			),
		);

		// Closing debit should transfer entire balance
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferTwo(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 0,
				ledger: 1,
				code: 1,
				flags: TransferFlags::CLOSING_DEBIT | TransferFlags::PENDING,
			),
		);

		$transfer = $this->transfers->ofId(TestIdentifiers::transferTwo())->one();

		// Should have transferred 3000 (entire balance)
		self::assertSame(3000, $transfer->amount->value);

		$debitAccount = $this->accounts->ofId(TestIdentifiers::accountOne())->one();
		self::assertSame(3000, $debitAccount->balance->creditsPosted->value);
		self::assertSame(3000, $debitAccount->balance->debitsPending->value);

		// TODO: Should we check for the account having the closed flag?
	}

	#[Test]
	public function it_ensures_transfer_and_balance_history_share_same_timestamp(): void
	{
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::HISTORY,
			),
			CreateAccount::with(
				id: TestIdentifiers::accountTwo(),
				ledger: 1,
				code: 200,
				flags: AccountFlags::HISTORY,
			),
		);

		// Create transfer
		$this->ledger->execute(
			CreateTransfer::with(
				id: TestIdentifiers::transferOne(),
				debitAccountId: TestIdentifiers::accountOne(),
				creditAccountId: TestIdentifiers::accountTwo(),
				amount: 1000,
				ledger: 1,
				code: 1,
			),
		);

		// Get balance history
		$debitHistory = $this->accountBalances->ofAccountId(TestIdentifiers::accountOne())->one();
		$creditHistory = $this->accountBalances->ofAccountId(TestIdentifiers::accountTwo())->one();

		$transfer = $this->transfers->ofId(TestIdentifiers::transferOne())->one();

		// All timestamps should be identical
		self::assertTrue($transfer->timestamp->equals($debitHistory->timestamp));
		self::assertTrue($transfer->timestamp->equals($creditHistory->timestamp));
	}

	#[Test]
	public function it_enforces_balance_constraints_on_pending_transfers(): void
	{
		$this->ledger->execute(
			CreateAccount::with(
				id: TestIdentifiers::accountOne(),
				ledger: 1,
				code: 100,
				flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
			),
			CreateAccount::with(id: TestIdentifiers::accountTwo(), ledger: 1, code: 200),
		);

		// Pending transfer should also fail constraint check
		$command = CreateTransfer::with(
			id: TestIdentifiers::pendingOne(),
			debitAccountId: TestIdentifiers::accountOne(),
			creditAccountId: TestIdentifiers::accountTwo(),
			amount: 1000,
			ledger: 1,
			code: 1,
			flags: TransferFlags::PENDING,
		);

		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::DebitsExceedCredits->value);

		$this->ledger->execute($command);
	}
}
