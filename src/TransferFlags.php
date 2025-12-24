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
 * Represents flags that control transfer behavior.
 *
 * Flags are immutable and can be combined using bitwise operations.
 */
final readonly class TransferFlags
{
	/**
	 * No special flags - a normal posted transfer.
	 */
	public const int NONE = 0;

	/**
	 * Creates a pending transfer.
	 *
	 * The transfer amount is moved to *_pending fields instead of *_posted.
	 * Must be posted or voided later to finalize.
	 */
	public const int PENDING = 1 << 0;

	/**
	 * Posts a pending transfer.
	 *
	 * Moves amount from *_pending to *_posted fields.
	 * Requires pendingId to reference the pending transfer.
	 */
	public const int POST_PENDING = 1 << 1;

	/**
	 * Voids a pending transfer.
	 *
	 * Removes amount from *_pending fields.
	 * Requires pendingId to reference the pending transfer.
	 */
	public const int VOID_PENDING = 1 << 2;

	/**
	 * Balancing debit transfer.
	 *
	 * The amount will be automatically calculated to balance the debit account.
	 * Can be combined with PENDING.
	 */
	public const int BALANCING_DEBIT = 1 << 3;

	/**
	 * Balancing credit transfer.
	 *
	 * The amount will be automatically calculated to balance the credit account.
	 * Can be combined with PENDING.
	 */
	public const int BALANCING_CREDIT = 1 << 4;

	/**
	 * Closing debit transfer.
	 *
	 * Transfers the entire debit account balance.
	 * Must be used with PENDING flag.
	 */
	public const int CLOSING_DEBIT = 1 << 5;

	/**
	 * Closing credit transfer.
	 *
	 * Transfers the entire credit account balance.
	 * Must be used with PENDING flag.
	 */
	public const int CLOSING_CREDIT = 1 << 6;

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
	 * @throws \InvalidArgumentException if flags are mutually exclusive
	 */
	public static function of(int $value): self
	{
		$instance = new self($value);

		// PENDING is mutually exclusive with POST_PENDING and VOID_PENDING
		if ($instance->isPending() && ($instance->isPostPending() || $instance->isVoidPending())) {
			throw new \InvalidArgumentException(
				'PENDING cannot be combined with POST_PENDING or VOID_PENDING',
			);
		}

		// POST_PENDING and VOID_PENDING are mutually exclusive
		if ($instance->isPostPending() && $instance->isVoidPending()) {
			throw new \InvalidArgumentException(
				'POST_PENDING and VOID_PENDING are mutually exclusive',
			);
		}

		// POST_PENDING and VOID_PENDING cannot be combined with balancing flags
		if (($instance->isPostPending() || $instance->isVoidPending()) &&
			($instance->isBalancingDebit() || $instance->isBalancingCredit())) {
			throw new \InvalidArgumentException(
				'POST_PENDING and VOID_PENDING cannot be combined with balancing flags',
			);
		}

		// Closing flags require PENDING
		if (($instance->isClosingDebit() || $instance->isClosingCredit()) && !$instance->isPending()) {
			throw new \InvalidArgumentException(
				'CLOSING_DEBIT and CLOSING_CREDIT require PENDING flag',
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
	 * Check if pending flag is set.
	 */
	public function isPending(): bool
	{
		return $this->has(self::PENDING);
	}

	/**
	 * Check if post pending flag is set.
	 */
	public function isPostPending(): bool
	{
		return $this->has(self::POST_PENDING);
	}

	/**
	 * Check if void pending flag is set.
	 */
	public function isVoidPending(): bool
	{
		return $this->has(self::VOID_PENDING);
	}

	/**
	 * Check if balancing debit flag is set.
	 */
	public function isBalancingDebit(): bool
	{
		return $this->has(self::BALANCING_DEBIT);
	}

	/**
	 * Check if balancing credit flag is set.
	 */
	public function isBalancingCredit(): bool
	{
		return $this->has(self::BALANCING_CREDIT);
	}

	/**
	 * Check if closing debit flag is set.
	 */
	public function isClosingDebit(): bool
	{
		return $this->has(self::CLOSING_DEBIT);
	}

	/**
	 * Check if closing credit flag is set.
	 */
	public function isClosingCredit(): bool
	{
		return $this->has(self::CLOSING_CREDIT);
	}

	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}
}
