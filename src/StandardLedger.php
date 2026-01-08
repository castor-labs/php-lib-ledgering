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
	public function execute(CreateAccount|CreateTransfer|ExpirePendingTransfers ...$commands): void
	{
		foreach ($commands as $command) {
			match ($command::class) {
				CreateAccount::class => $this->createAccount($command),
				CreateTransfer::class => $this->createTransfer($command),
				ExpirePendingTransfers::class => $this->expirePendingTransfers($command),
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
		$existing = $this->accounts->ofId($command->id)->first();
		if ($existing !== null) {
			throw ConstraintViolation::accountAlreadyExists($command->id);
		}

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

		$this->accounts->write($account);
	}

	/**
	 * Create a new transfer in the ledger.
	 *
	 * Dispatches to the appropriate handler based on transfer flags.
	 *
	 * @throws ConstraintViolation if any business rule is violated
	 */
	private function createTransfer(CreateTransfer $command): void
	{
		$this->validateTransfer($command);

		if ($command->flags->isPostPending()) {
			$this->postPendingTransfer($command);

			return;
		}

		if ($command->flags->isVoidPending()) {
			$this->voidPendingTransfer($command);

			return;
		}

		$this->executeTransfer($command);
	}

	/**
	 * Validate transfer command.
	 *
	 * @throws ConstraintViolation
	 */
	private function validateTransfer(CreateTransfer $command): void
	{
		if ($command->debitAccountId->equals($command->creditAccountId)) {
			throw ConstraintViolation::sameDebitAndCreditAccount($command->debitAccountId);
		}

		if ($command->amount->isZero() &&
			!$command->flags->isBalancingDebit() &&
			!$command->flags->isBalancingCredit() &&
			!$command->flags->isClosingDebit() &&
			!$command->flags->isClosingCredit() &&
			!$command->flags->isPostPending() &&
			!$command->flags->isVoidPending()) {
			throw ConstraintViolation::zeroAmount();
		}

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
		$existing = $this->transfers->ofId($command->id)->first();
		if ($existing !== null) {
			throw ConstraintViolation::transferAlreadyExists($command->id);
		}

		$debitAccount = $this->loadAccount($command->debitAccountId);
		$creditAccount = $this->loadAccount($command->creditAccountId);

		$this->validateAccountNotClosed($debitAccount);
		$this->validateAccountNotClosed($creditAccount);

		if (!$debitAccount->ledger->equals($creditAccount->ledger)) {
			throw ConstraintViolation::ledgerMismatch($debitAccount->ledger, $creditAccount->ledger);
		}

		$amount = $this->calculateAmount($command, $debitAccount, $creditAccount);

		$timestamp = $this->clock->now();

		[$updatedDebitAccount, $updatedCreditAccount] = $this->updateBalances(
			$debitAccount,
			$creditAccount,
			$amount,
			$command->flags->isPending(),
			$timestamp,
		);

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

		$this->accounts->write($updatedDebitAccount);
		$this->accounts->write($updatedCreditAccount);
		$this->transfers->write($transfer);

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
		$existing = $this->transfers->ofId($command->id)->first();
		if ($existing !== null) {
			throw ConstraintViolation::transferAlreadyExists($command->id);
		}

		$pendingTransfer = $this->loadPendingTransfer($command->pendingId);

		$debitAccount = $this->loadAccount($pendingTransfer->debitAccountId);
		$creditAccount = $this->loadAccount($pendingTransfer->creditAccountId);

		// Determine the amount to post
		// If command amount is 0, post the full pending amount
		// Otherwise, post the specified amount (partial posting)
		$amountToPost = $command->amount->isZero()
			? $pendingTransfer->amount
			: $command->amount;

		// Validate that the amount doesn't exceed the pending amount
		if ($amountToPost->value > $pendingTransfer->amount->value) {
			throw ConstraintViolation::exceedsPendingTransferAmount();
		}

		$timestamp = $this->clock->now();

		[$updatedDebitAccount, $updatedCreditAccount] = $this->postPending(
			$debitAccount,
			$creditAccount,
			$amountToPost,
			$timestamp,
		);

		// Set CLOSED flag if this is a closing transfer
		if ($pendingTransfer->flags->isClosingDebit()) {
			$updatedDebitAccount = new Account(
				id: $updatedDebitAccount->id,
				ledger: $updatedDebitAccount->ledger,
				code: $updatedDebitAccount->code,
				flags: $updatedDebitAccount->flags->with(AccountFlags::CLOSED),
				externalIdPrimary: $updatedDebitAccount->externalIdPrimary,
				externalIdSecondary: $updatedDebitAccount->externalIdSecondary,
				externalCodePrimary: $updatedDebitAccount->externalCodePrimary,
				balance: $updatedDebitAccount->balance,
				timestamp: $updatedDebitAccount->timestamp,
			);
		}

		if ($pendingTransfer->flags->isClosingCredit()) {
			$updatedCreditAccount = new Account(
				id: $updatedCreditAccount->id,
				ledger: $updatedCreditAccount->ledger,
				code: $updatedCreditAccount->code,
				flags: $updatedCreditAccount->flags->with(AccountFlags::CLOSED),
				externalIdPrimary: $updatedCreditAccount->externalIdPrimary,
				externalIdSecondary: $updatedCreditAccount->externalIdSecondary,
				externalCodePrimary: $updatedCreditAccount->externalCodePrimary,
				balance: $updatedCreditAccount->balance,
				timestamp: $updatedCreditAccount->timestamp,
			);
		}

		$transfer = new Transfer(
			id: $command->id,
			debitAccountId: $pendingTransfer->debitAccountId,
			creditAccountId: $pendingTransfer->creditAccountId,
			amount: $amountToPost,
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

		$this->accounts->write($updatedDebitAccount);
		$this->accounts->write($updatedCreditAccount);
		$this->transfers->write($transfer);

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
		$existing = $this->transfers->ofId($command->id)->first();
		if ($existing !== null) {
			throw ConstraintViolation::transferAlreadyExists($command->id);
		}

		$pendingTransfer = $this->loadPendingTransfer($command->pendingId);

		$debitAccount = $this->loadAccount($pendingTransfer->debitAccountId);
		$creditAccount = $this->loadAccount($pendingTransfer->creditAccountId);

		$timestamp = $this->clock->now();

		[$updatedDebitAccount, $updatedCreditAccount] = $this->voidPending(
			$debitAccount,
			$creditAccount,
			$pendingTransfer->amount,
			$timestamp,
		);

		// Remove CLOSED flag if this was a closing transfer
		if ($pendingTransfer->flags->isClosingDebit()) {
			$updatedDebitAccount = new Account(
				id: $updatedDebitAccount->id,
				ledger: $updatedDebitAccount->ledger,
				code: $updatedDebitAccount->code,
				flags: $updatedDebitAccount->flags->without(AccountFlags::CLOSED),
				externalIdPrimary: $updatedDebitAccount->externalIdPrimary,
				externalIdSecondary: $updatedDebitAccount->externalIdSecondary,
				externalCodePrimary: $updatedDebitAccount->externalCodePrimary,
				balance: $updatedDebitAccount->balance,
				timestamp: $updatedDebitAccount->timestamp,
			);
		}

		if ($pendingTransfer->flags->isClosingCredit()) {
			$updatedCreditAccount = new Account(
				id: $updatedCreditAccount->id,
				ledger: $updatedCreditAccount->ledger,
				code: $updatedCreditAccount->code,
				flags: $updatedCreditAccount->flags->without(AccountFlags::CLOSED),
				externalIdPrimary: $updatedCreditAccount->externalIdPrimary,
				externalIdSecondary: $updatedCreditAccount->externalIdSecondary,
				externalCodePrimary: $updatedCreditAccount->externalCodePrimary,
				balance: $updatedCreditAccount->balance,
				timestamp: $updatedCreditAccount->timestamp,
			);
		}

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

		$this->accounts->write($updatedDebitAccount);
		$this->accounts->write($updatedCreditAccount);
		$this->transfers->write($transfer);

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
	 * Expire all pending transfers that have exceeded their timeout.
	 *
	 * Uses the expired() filter which efficiently queries for pending transfers
	 * with non-zero timeouts that have exceeded (timestamp + timeout) <= asOf.
	 */
	private function expirePendingTransfers(ExpirePendingTransfers $command): void
	{
		$expiredTransfers = $this->transfers->expired($command->asOf)->toList();

		foreach ($expiredTransfers as $transfer) {
			$this->voidPendingTransfer(
				CreateTransfer::with(
					id: Identifier::random(),
					debitAccountId: $transfer->debitAccountId,
					creditAccountId: $transfer->creditAccountId,
					amount: 0,
					ledger: $transfer->ledger,
					code: $transfer->code,
					flags: TransferFlags::VOID_PENDING,
					pendingId: $transfer->id,
				),
			);
		}
	}

	/**
	 * Load a pending transfer by ID.
	 *
	 * @throws ConstraintViolation if pending transfer isn't found or not pending
	 */
	private function loadPendingTransfer(Identifier $pendingId): Transfer
	{
		$transfer = $this->transfers->ofId($pendingId)->first();
		if ($transfer === null || !$transfer->flags->isPending()) {
			throw ConstraintViolation::pendingTransferNotFound($pendingId);
		}

		if (!$transfer->timeout->isZero() && $this->isTransferExpired($transfer, $this->clock->now())) {
			throw ConstraintViolation::pendingTransferExpired($pendingId);
		}

		$postOrVoid = $this->transfers->ofPendingId($pendingId)->first();
		if ($postOrVoid !== null) {
			if ($postOrVoid->flags->isPostPending()) {
				throw ConstraintViolation::pendingTransferAlreadyPosted($pendingId);
			}

			if ($postOrVoid->flags->isVoidPending()) {
				throw ConstraintViolation::pendingTransferAlreadyVoided($pendingId);
			}
		}

		return $transfer;
	}

	/**
	 * Check if a transfer has expired based on its timeout.
	 */
	private function isTransferExpired(Transfer $transfer, Instant $asOf): bool
	{
		$expirationSeconds = $transfer->timestamp->seconds + $transfer->timeout->seconds;

		return $asOf->seconds >= $expirationSeconds;
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
	 * Calculate the transfer amount.
	 *
	 * For balancing and closing transfers, the amount is computed from account balances.
	 * Otherwise, uses the amount from the command.
	 */
	private function calculateAmount(
		CreateTransfer $command,
		Account $debitAccount,
		Account $creditAccount,
	): Amount {
		// When both BALANCING_DEBIT and BALANCING_CREDIT are set,
		// use the minimum of what's available in debit and what's needed in credit
		if ($command->flags->isBalancingDebit() && $command->flags->isBalancingCredit()) {
			$debitAmount = $this->calculateBalancingAmount($debitAccount);
			$creditAmount = $this->calculateBalancingAmount($creditAccount);

			return $debitAmount->value < $creditAmount->value ? $debitAmount : $creditAmount;
		}

		if ($command->flags->isBalancingDebit()) {
			return $this->calculateBalancingAmount($debitAccount);
		}

		if ($command->flags->isBalancingCredit()) {
			return $this->calculateBalancingAmount($creditAccount);
		}

		if ($command->flags->isClosingDebit()) {
			return $this->calculateClosingAmount($debitAccount);
		}

		if ($command->flags->isClosingCredit()) {
			return $this->calculateClosingAmount($creditAccount);
		}

		return $command->amount;
	}

	/**
	 * Calculate balancing amount.
	 *
	 * Returns the absolute net balance (total debits - total credits).
	 */
	private function calculateBalancingAmount(Account $account): Amount
	{
		$debits = $account->balance->debitsPosted->value + $account->balance->debitsPending->value;
		$credits = $account->balance->creditsPosted->value + $account->balance->creditsPending->value;

		return Amount::of(\abs($debits - $credits));
	}

	/**
	 * Calculate closing amount.
	 *
	 * Returns the sum of all balance components (debits + credits, posted + pending).
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

		$this->validateBalanceConstraints($debitAccount, $newDebitBalance);
		$this->validateBalanceConstraints($creditAccount, $newCreditBalance);

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
		if ($debitAccount->balance->debitsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($debitAccount->id);
		}

		if ($creditAccount->balance->creditsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($creditAccount->id);
		}

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

		$this->validateBalanceConstraints($debitAccount, $newDebitBalance);
		$this->validateBalanceConstraints($creditAccount, $newCreditBalance);

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
		if ($debitAccount->balance->debitsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($debitAccount->id);
		}

		if ($creditAccount->balance->creditsPending->compare($amount) < 0) {
			throw ConstraintViolation::insufficientPendingBalance($creditAccount->id);
		}

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
		if ($account->flags->debitsMusNotExceedCredits()) {
			$totalDebits = $newBalance->debitsPosted->value + $newBalance->debitsPending->value;
			$postedCredits = $newBalance->creditsPosted->value;

			if ($totalDebits > $postedCredits) {
				throw ConstraintViolation::debitsExceedCredits($account->id);
			}
		}

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
