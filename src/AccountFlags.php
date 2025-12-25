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

/**
 * Represents flags that control account behavior.
 *
 * Flags are immutable and can be combined using bitwise operations.
 */
final readonly class AccountFlags
{
	/**
	 * No special flags.
	 */
	public const int NONE = 0;

	/**
	 * Prevents the account from having more debits than credits.
	 *
	 * Constraint: debits_posted + debits_pending ≤ credits_posted
	 *
	 * Use case: Customer cash accounts (can't spend more than deposited)
	 */
	public const int DEBITS_MUST_NOT_EXCEED_CREDITS = 1 << 0;

	/**
	 * Prevents the account from having more credits than debits.
	 *
	 * Constraint: credits_posted + credits_pending ≤ debits_posted
	 *
	 * Use case: Loan accounts (can't repay more than borrowed)
	 */
	public const int CREDITS_MUST_NOT_EXCEED_DEBITS = 1 << 1;

	/**
	 * Enables historical balance tracking for this account.
	 *
	 * When enabled, balance snapshots are recorded after each transfer.
	 * This adds storage overhead but allows querying historical balances.
	 */
	public const int HISTORY = 1 << 2;

	/**
	 * Marks the account as closed.
	 *
	 * No new transfers can debit or credit this account.
	 */
	public const int CLOSED = 1 << 3;

	private function __construct(
		public int $value,
	) {}

	public function __toString(): string
	{
		return (string) $this->value;
	}

	/**
	 * Create flags from an integer value.
	 *
	 * @throws ConstraintViolation if the flags are invalid
	 */
	public static function of(int $value): self
	{
		$instance = new self($value);

		// Validate that flags are not mutually exclusive
		if ($instance->debitsMusNotExceedCredits() && $instance->creditsMusNotExceedDebits()) {
			throw ConstraintViolation::mutuallyExclusiveFlags(
				'DEBITS_MUST_NOT_EXCEED_CREDITS',
				'CREDITS_MUST_NOT_EXCEED_DEBITS',
			);
		}

		return $instance;
	}

	/**
	 * Create flags with no special behavior.
	 */
	public static function none(): self
	{
		return new self(self::NONE);
	}

	/**
	 * Get the raw integer value.
	 */
	public function toInt(): int
	{
		return $this->value;
	}

	/**
	 * Check if a specific flag is set.
	 */
	public function has(int $flag): bool
	{
		return ($this->value & $flag) === $flag;
	}

	/**
	 * Add a flag to the current flags.
	 */
	public function with(int $flag): self
	{
		return new self($this->value | $flag);
	}

	/**
	 * Remove a flag from the current flags.
	 */
	public function without(int $flag): self
	{
		return new self($this->value & ~$flag);
	}

	/**
	 * Check if debits must not exceed credits flag is set.
	 */
	public function debitsMusNotExceedCredits(): bool
	{
		return $this->has(self::DEBITS_MUST_NOT_EXCEED_CREDITS);
	}

	/**
	 * Check if credits must not exceed debits flag is set.
	 */
	public function creditsMusNotExceedDebits(): bool
	{
		return $this->has(self::CREDITS_MUST_NOT_EXCEED_DEBITS);
	}

	/**
	 * Check if history flag is set.
	 */
	public function hasHistory(): bool
	{
		return $this->has(self::HISTORY);
	}

	/**
	 * Check if closed flag is set.
	 */
	public function isClosed(): bool
	{
		return $this->has(self::CLOSED);
	}

	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}
}
