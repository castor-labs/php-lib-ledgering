<?php

declare(strict_types=1);

namespace Castor\Ledgering;

/**
 * Idempotent ledger decorator.
 *
 * Wraps another Ledger implementation and automatically suppresses
 * AccountAlreadyExists and TransferAlreadyExists errors, making
 * operations idempotent and safe for retries.
 *
 * This is useful in distributed systems where you need to retry
 * operations without worrying about duplicate creation errors.
 *
 * Example:
 * ```php
 * $ledger = new IdempotentLedger(
 *     new StandardLedger($accounts, $transfers, $accountBalances)
 * );
 *
 * // Safe to retry - won't throw if account already exists
 * $ledger->execute($createAccount);
 * $ledger->execute($createAccount);  // No error!
 * ```
 */
final readonly class IdempotentLedger implements Ledger
{
	public function __construct(
		private Ledger $ledger,
	) {}

	#[\Override]
	public function execute(CreateAccount|CreateTransfer|ExpirePendingTransfers ...$commands): void
	{
		try {
			$this->ledger->execute(...$commands);
		} catch (ConstraintViolation $e) {
			// Suppress duplicate errors - these are safe to ignore for idempotency
			match ($e->errorCode) {
				ErrorCode::AccountAlreadyExists, ErrorCode::TransferAlreadyExists => null,
				default => throw $e,
			};
		}
	}
}
