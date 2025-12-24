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

use Castor\Ledgering\Storage\AccountBalanceReader;
use Castor\Ledgering\Storage\AccountBalanceWriter;
use Castor\Ledgering\Storage\AccountReader;
use Castor\Ledgering\Storage\AccountWriter;
use Castor\Ledgering\Storage\TransferReader;
use Castor\Ledgering\Storage\TransferWriter;
use Castor\Ledgering\Time\Clock;
use Castor\Ledgering\Time\DefaultClock;
use Castor\Ledgering\Time\Instant;

/**
 * Standard ledger implementation that enforces all business rules and invariants.
 *
 * The StandardLedger is responsible for:
 * - Creating accounts and transfers
 * - Enforcing balance constraints
 * - Managing pending transfers
 * - Recording balance history
 * - Validating all operations
 */
final readonly class StandardLedger implements Ledger
{
	public function __construct(
		private AccountReader&AccountWriter $accounts,
		private TransferReader&TransferWriter $transfers,
		private AccountBalanceReader&AccountBalanceWriter $accountBalances,
		private Clock $clock = new DefaultClock(),
	) {}

	#[\Override]
	public function execute(CreateAccount|CreateTransfer ...$commands): void
	{
		foreach ($commands as $command) {
			match ($command::class) {
				CreateAccount::class => $this->createAccount($command),
				CreateTransfer::class => $this->createTransfer($command),
			};
		}
	}

	/**
	 * Create a new account in the ledger.
	 *
	 * @throws ConstraintViolation if account already exists
	 */
	private function createAccount(CreateAccount $command): void
	{
		// Check if account already exists
		$existing = $this->accounts->ofId($command->id)->first();
		if ($existing !== null) {
			throw ConstraintViolation::accountAlreadyExists($command->id);
		}

		// Create new account with zero balance
		$account = new Account(
			id: $command->id,
			ledger: $command->ledger,
			code: $command->code,
			flags: $command->flags,
			externalIdPrimary: $command->externalIdPrimary,
			externalIdSecondary: $command->externalIdSecondary,
			externalCodePrimary: $command->externalCodePrimary,
			balance: Balance::zero(),
			timestamp: $this->clock->now(),
		);

		// Persist the account
		$this->accounts->write($account);
	}

	/**
	 * Create a new transfer in the ledger.
	 *
	 * @throws ConstraintViolation if any business rule is violated
	 */
	private function createTransfer(CreateTransfer $command): void
	{
		// Validate the transfer command
		$this->validateTransfer($command);

		// Handle different transfer types
		if ($command->flags->isPostPending()) {
			$this->postPendingTransfer($command);

			return;
		}

		if ($command->flags->isVoidPending()) {
			$this->voidPendingTransfer($command);

			return;
		}

		// Regular or pending transfer
		$this->executeTransfer($command);
	}

	/**
	 * Validate transfer command.
	 *
	 * @throws ConstraintViolation
	 */
	private function validateTransfer(CreateTransfer $command): void
	{
		// Cannot transfer to/from the same account
		if ($command->debitAccountId->equals($command->creditAccountId)) {
			throw ConstraintViolation::sameDebitAndCreditAccount($command->debitAccountId);
		}

		// Amount must be non-zero (unless balancing/closing/post/void pending)
		if ($command->amount->isZero() &&
			!$command->flags->isBalancingDebit() &&
			!$command->flags->isBalancingCredit() &&
			!$command->flags->isClosingDebit() &&
			!$command->flags->isClosingCredit() &&
			!$command->flags->isPostPending() &&
			!$command->flags->isVoidPending()) {
			throw ConstraintViolation::zeroAmount();
		}

		// POST_PENDING and VOID_PENDING require pendingId
		if (($command->flags->isPostPending() || $command->flags->isVoidPending()) &&
			$command->pendingId->isZero()) {
			throw ConstraintViolation::pendingIdRequired();
		}
	}

	/**
	 * Execute a regular or pending transfer.
	 *
	 * @throws ConstraintViolation
	 */
	private function executeTransfer(CreateTransfer $command): void
	{
		// Check if transfer already exists (idempotency)
		$existing = $this->transfers->ofId($command->id)->first();
		if ($existing !== null) {
			return;
		}

		// Load both accounts
		$debitAccount = $this->loadAccount($command->debitAccountId);
		$creditAccount = $this->loadAccount($command->creditAccountId);

		// Validate accounts are not closed
		$this->validateAccountNotClosed($debitAccount);
		$this->validateAccountNotClosed($creditAccount);

		// Validate ledgers match
		if (!$debitAccount->ledger->equals($creditAccount->ledger)) {
			throw ConstraintViolation::ledgerMismatch($debitAccount->ledger, $creditAccount->ledger);
		}

		// Calculate amount (for balancing/closing transfers)
		$amount = $this->calculateAmount($command, $debitAccount, $creditAccount);

		// Capture timestamp once for correlation
		$timestamp = $this->clock->now();

		// Update account balances
		[$updatedDebitAccount, $updatedCreditAccount] = $this->updateBalances(
			$debitAccount,
			$creditAccount,
			$amount,
			$command->flags->isPending(),
			$timestamp,
		);

		// Create the transfer
		$transfer = new Transfer(
			id: $command->id,
			debitAccountId: $command->debitAccountId,
			creditAccountId: $command->creditAccountId,
			amount: $amount,
			pendingId: $command->pendingId,
			ledger: $command->ledger,
			code: $command->code,
			flags: $command->flags,
			timeout: $command->timeout,
			externalIdPrimary: $command->externalIdPrimary,
			externalIdSecondary: $command->externalIdSecondary,
			externalCodePrimary: $command->externalCodePrimary,
			timestamp: $timestamp,
		);

		// Persist everything
		$this->accounts->write($updatedDebitAccount);
		$this->accounts->write($updatedCreditAccount);
		$this->transfers->write($transfer);

		// Record balance history if enabled
		$this->recordBalanceHistory($updatedDebitAccount);
		$this->recordBalanceHistory($updatedCreditAccount);
	}

	/**
	 * Post a pending transfer.
	 *
	 * @throws ConstraintViolation
	 */
	private function postPendingTransfer(CreateTransfer $command): void
	{
		// Load the pending transfer
		$pendingTransfer = $this->loadPendingTransfer($command->pendingId);

		// Load both accounts
		$debitAccount = $this->loadAccount($pendingTransfer->debitAccountId);
		$creditAccount = $this->loadAccount($pendingTransfer->creditAccountId);

		// Capture timestamp once for correlation
		$timestamp = $this->clock->now();

		// Move from pending to posted
		[$updatedDebitAccount, $updatedCreditAccount] = $this->postPending(
			$debitAccount,
			$creditAccount,
			$pendingTransfer->amount,
			$timestamp,
		);

		// Create the post transfer
		$transfer = new Transfer(
			id: $command->id,
			debitAccountId: $pendingTransfer->debitAccountId,
			creditAccountId: $pendingTransfer->creditAccountId,
			amount: $pendingTransfer->amount,
			pendingId: $command->pendingId,
			ledger: $command->ledger,
			code: $command->code,
			flags: $command->flags,
			timeout: $command->timeout,
			externalIdPrimary: $command->externalIdPrimary,
			externalIdSecondary: $command->externalIdSecondary,
			externalCodePrimary: $command->externalCodePrimary,
			timestamp: $timestamp,
		);

		// Persist everything
		$this->accounts->write($updatedDebitAccount);
		$this->accounts->write($updatedCreditAccount);
		$this->transfers->write($transfer);

		// Record balance history if enabled
		$this->recordBalanceHistory($updatedDebitAccount);
		$this->recordBalanceHistory($updatedCreditAccount);
	}

	/**
	 * Void a pending transfer.
	 *
	 * @throws ConstraintViolation
	 */
	private function voidPendingTransfer(CreateTransfer $command): void
	{
		// Load the pending transfer
		$pendingTransfer = $this->loadPendingTransfer($command->pendingId);

		// Load both accounts
		$debitAccount = $this->loadAccount($pendingTransfer->debitAccountId);
		$creditAccount = $this->loadAccount($pendingTransfer->creditAccountId);

		// Capture timestamp once for correlation
		$timestamp = $this->clock->now();

		// Remove from pending
		[$updatedDebitAccount, $updatedCreditAccount] = $this->voidPending(
			$debitAccount,
			$creditAccount,
			$pendingTransfer->amount,
			$timestamp,
		);

		// Create the void transfer
		$transfer = new Transfer(
			id: $command->id,
			debitAccountId: $pendingTransfer->debitAccountId,
			creditAccountId: $pendingTransfer->creditAccountId,
			amount: $pendingTransfer->amount,
			pendingId: $command->pendingId,
			ledger: $command->ledger,
			code: $command->code,
			flags: $command->flags,
			timeout: $command->timeout,
			externalIdPrimary: $command->externalIdPrimary,
			externalIdSecondary: $command->externalIdSecondary,
			externalCodePrimary: $command->externalCodePrimary,
			timestamp: $timestamp,
		);

		// Persist everything
		$this->accounts->write($updatedDebitAccount);
		$this->accounts->write($updatedCreditAccount);
		$this->transfers->write($transfer);

		// Record balance history if enabled
		$this->recordBalanceHistory($updatedDebitAccount);
		$this->recordBalanceHistory($updatedCreditAccount);
	}

	/**
	 * Load an account by ID.
	 *
	 * @throws ConstraintViolation if account not found
	 */
	private function loadAccount(Identifier $id): Account
	{
		$account = $this->accounts->ofId($id)->first();
		if ($account === null) {
			throw ConstraintViolation::accountNotFound($id);
		}

		return $account;
	}

	/**
	 * Load a pending transfer by ID.
	 *
	 * @throws ConstraintViolation if pending transfer not found or not pending
	 */
	private function loadPendingTransfer(Identifier $pendingId): Transfer
	{
		$transfer = $this->transfers->ofId($pendingId)->first();
		if ($transfer === null || !$transfer->flags->isPending()) {
			throw ConstraintViolation::pendingTransferNotFound($pendingId);
		}

		// TODO: Check timeout expiration

		return $transfer;
	}

	/**
	 * Validate that an account is not closed.
	 *
	 * @throws ConstraintViolation if account is closed
	 */
	private function validateAccountNotClosed(Account $account): void
	{
		if ($account->flags->isClosed()) {
			throw ConstraintViolation::accountClosed($account->id);
		}
	}

	/**
	 * Calculate the transfer amount (handles balancing and closing transfers).
	 */
	private function calculateAmount(
		CreateTransfer $command,
		Account $debitAccount,
		Account $creditAccount,
	): Amount {
		// Balancing debit: calculate amount to zero out debit account
		if ($command->flags->isBalancingDebit()) {
			return $this->calculateBalancingAmount($debitAccount);
		}

		// Balancing credit: calculate amount to zero out credit account
		if ($command->flags->isBalancingCredit()) {
			return $this->calculateBalancingAmount($creditAccount);
		}

		// Closing debit: transfer entire debit account balance
		if ($command->flags->isClosingDebit()) {
			return $this->calculateClosingAmount($debitAccount);
		}

		// Closing credit: transfer entire credit account balance
		if ($command->flags->isClosingCredit()) {
			return $this->calculateClosingAmount($creditAccount);
		}

		// Use the provided amount
		return $command->amount;
	}

	/**
	 * Calculate balancing amount (net balance).
	 */
	private function calculateBalancingAmount(Account $account): Amount
	{
		$debits = $account->balance->debitsPosted->value + $account->balance->debitsPending->value;
		$credits = $account->balance->creditsPosted->value + $account->balance->creditsPending->value;

		return Amount::of(\abs($debits - $credits));
	}

	/**
	 * Calculate closing amount (total balance).
	 */
	private function calculateClosingAmount(Account $account): Amount
	{
		return $account->balance->debitsPosted
			->add($account->balance->debitsPending)
			->add($account->balance->creditsPosted)
			->add($account->balance->creditsPending);
	}

	/**
	 * Update account balances for a transfer.
	 *
	 * @return array{Account, Account}
	 *
	 * @throws ConstraintViolation if balance constraints are violated
	 */
	private function updateBalances(
		Account $debitAccount,
		Account $creditAccount,
		Amount $amount,
		bool $isPending,
		Instant $timestamp,
	): array {
		// Calculate new balances
		if ($isPending) {
			$newDebitBalance = new Balance(
				debitsPosted: $debitAccount->balance->debitsPosted,
				creditsPosted: $debitAccount->balance->creditsPosted,
				debitsPending: $debitAccount->balance->debitsPending->add($amount),
				creditsPending: $debitAccount->balance->creditsPending,
			);

			$newCreditBalance = new Balance(
				debitsPosted: $creditAccount->balance->debitsPosted,
				creditsPosted: $creditAccount->balance->creditsPosted,
				debitsPending: $creditAccount->balance->debitsPending,
				creditsPending: $creditAccount->balance->creditsPending->add($amount),
			);
		} else {
			$newDebitBalance = new Balance(
				debitsPosted: $debitAccount->balance->debitsPosted->add($amount),
				creditsPosted: $debitAccount->balance->creditsPosted,
				debitsPending: $debitAccount->balance->debitsPending,
				creditsPending: $debitAccount->balance->creditsPending,
			);

			$newCreditBalance = new Balance(
				debitsPosted: $creditAccount->balance->debitsPosted,
				creditsPosted: $creditAccount->balance->creditsPosted->add($amount),
				debitsPending: $creditAccount->balance->debitsPending,
				creditsPending: $creditAccount->balance->creditsPending,
			);
		}

		// Validate constraints
		$this->validateBalanceConstraints($debitAccount, $newDebitBalance);
		$this->validateBalanceConstraints($creditAccount, $newCreditBalance);

		// Create updated accounts
		$updatedDebitAccount = new Account(
			id: $debitAccount->id,
			ledger: $debitAccount->ledger,
			code: $debitAccount->code,
			flags: $debitAccount->flags,
			externalIdPrimary: $debitAccount->externalIdPrimary,
			externalIdSecondary: $debitAccount->externalIdSecondary,
			externalCodePrimary: $debitAccount->externalCodePrimary,
			balance: $newDebitBalance,
			timestamp: $timestamp,
		);

		$updatedCreditAccount = new Account(
			id: $creditAccount->id,
			ledger: $creditAccount->ledger,
			code: $creditAccount->code,
			flags: $creditAccount->flags,
			externalIdPrimary: $creditAccount->externalIdPrimary,
			externalIdSecondary: $creditAccount->externalIdSecondary,
			externalCodePrimary: $creditAccount->externalCodePrimary,
			balance: $newCreditBalance,
			timestamp: $timestamp,
		);

		return [$updatedDebitAccount, $updatedCreditAccount];
	}

	/**
	 * Post a pending transfer (move from pending to posted).
	 *
	 * @return array{Account, Account}
	 *
	 * @throws ConstraintViolation
	 */
	private function postPending(
		Account $debitAccount,
		Account $creditAccount,
		Amount $amount,
		Instant $timestamp,
	): array {
		// Verify sufficient pending balance
		if ($debitAccount->balance->debitsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($debitAccount->id);
		}

		if ($creditAccount->balance->creditsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($creditAccount->id);
		}

		// Move from pending to posted
		$newDebitBalance = new Balance(
			debitsPosted: $debitAccount->balance->debitsPosted->add($amount),
			creditsPosted: $debitAccount->balance->creditsPosted,
			debitsPending: $debitAccount->balance->debitsPending->subtract($amount),
			creditsPending: $debitAccount->balance->creditsPending,
		);

		$newCreditBalance = new Balance(
			debitsPosted: $creditAccount->balance->debitsPosted,
			creditsPosted: $creditAccount->balance->creditsPosted->add($amount),
			debitsPending: $creditAccount->balance->debitsPending,
			creditsPending: $creditAccount->balance->creditsPending->subtract($amount),
		);

		// Validate constraints (should always pass since pending already validated)
		$this->validateBalanceConstraints($debitAccount, $newDebitBalance);
		$this->validateBalanceConstraints($creditAccount, $newCreditBalance);

		// Create updated accounts
		$updatedDebitAccount = new Account(
			id: $debitAccount->id,
			ledger: $debitAccount->ledger,
			code: $debitAccount->code,
			flags: $debitAccount->flags,
			externalIdPrimary: $debitAccount->externalIdPrimary,
			externalIdSecondary: $debitAccount->externalIdSecondary,
			externalCodePrimary: $debitAccount->externalCodePrimary,
			balance: $newDebitBalance,
			timestamp: $timestamp,
		);

		$updatedCreditAccount = new Account(
			id: $creditAccount->id,
			ledger: $creditAccount->ledger,
			code: $creditAccount->code,
			flags: $creditAccount->flags,
			externalIdPrimary: $creditAccount->externalIdPrimary,
			externalIdSecondary: $creditAccount->externalIdSecondary,
			externalCodePrimary: $creditAccount->externalCodePrimary,
			balance: $newCreditBalance,
			timestamp: $timestamp,
		);

		return [$updatedDebitAccount, $updatedCreditAccount];
	}

	/**
	 * Void a pending transfer (remove from pending).
	 *
	 * @return array{Account, Account}
	 *
	 * @throws ConstraintViolation
	 */
	private function voidPending(
		Account $debitAccount,
		Account $creditAccount,
		Amount $amount,
		Instant $timestamp,
	): array {
		// Verify sufficient pending balance
		if ($debitAccount->balance->debitsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($debitAccount->id);
		}

		if ($creditAccount->balance->creditsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($creditAccount->id);
		}

		// Remove from pending
		$newDebitBalance = new Balance(
			debitsPosted: $debitAccount->balance->debitsPosted,
			creditsPosted: $debitAccount->balance->creditsPosted,
			debitsPending: $debitAccount->balance->debitsPending->subtract($amount),
			creditsPending: $debitAccount->balance->creditsPending,
		);

		$newCreditBalance = new Balance(
			debitsPosted: $creditAccount->balance->debitsPosted,
			creditsPosted: $creditAccount->balance->creditsPosted,
			debitsPending: $creditAccount->balance->debitsPending,
			creditsPending: $creditAccount->balance->creditsPending->subtract($amount),
		);

		// Create updated accounts
		$updatedDebitAccount = new Account(
			id: $debitAccount->id,
			ledger: $debitAccount->ledger,
			code: $debitAccount->code,
			flags: $debitAccount->flags,
			externalIdPrimary: $debitAccount->externalIdPrimary,
			externalIdSecondary: $debitAccount->externalIdSecondary,
			externalCodePrimary: $debitAccount->externalCodePrimary,
			balance: $newDebitBalance,
			timestamp: $timestamp,
		);

		$updatedCreditAccount = new Account(
			id: $creditAccount->id,
			ledger: $creditAccount->ledger,
			code: $creditAccount->code,
			flags: $creditAccount->flags,
			externalIdPrimary: $creditAccount->externalIdPrimary,
			externalIdSecondary: $creditAccount->externalIdSecondary,
			externalCodePrimary: $creditAccount->externalCodePrimary,
			balance: $newCreditBalance,
			timestamp: $timestamp,
		);

		return [$updatedDebitAccount, $updatedCreditAccount];
	}

	/**
	 * Validate balance constraints for an account.
	 *
	 * @throws ConstraintViolation if constraints are violated
	 */
	private function validateBalanceConstraints(Account $account, Balance $newBalance): void
	{
		// Check DEBITS_MUST_NOT_EXCEED_CREDITS constraint
		if ($account->flags->debitsMusNotExceedCredits()) {
			$totalDebits = $newBalance->debitsPosted->value + $newBalance->debitsPending->value;
			$postedCredits = $newBalance->creditsPosted->value;

			if ($totalDebits > $postedCredits) {
				throw ConstraintViolation::debitsExceedCredits($account->id);
			}
		}

		// Check CREDITS_MUST_NOT_EXCEED_DEBITS constraint
		if ($account->flags->creditsMusNotExceedDebits()) {
			$totalCredits = $newBalance->creditsPosted->value + $newBalance->creditsPending->value;
			$postedDebits = $newBalance->debitsPosted->value;

			if ($totalCredits > $postedDebits) {
				throw ConstraintViolation::creditsExceedDebits($account->id);
			}
		}
	}

	/**
	 * Record balance history if account has HISTORY flag.
	 */
	private function recordBalanceHistory(Account $account): void
	{
		if (!$account->flags->hasHistory()) {
			return;
		}

		$accountBalance = new AccountBalance(
			accountId: $account->id,
			balance: $account->balance,
			timestamp: $account->timestamp,
		);

		$this->accountBalances->write($accountBalance);
	}
}
