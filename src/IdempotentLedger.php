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
				ErrorCode::AccountAlreadyExists,
				ErrorCode::TransferAlreadyExists => null,
				default => throw $e,
			};
		}
	}
}
