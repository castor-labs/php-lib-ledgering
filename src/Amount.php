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
 * Represents a non-negative amount in the ledger.
 *
 * Amounts are immutable and cannot be negative.
 */
final readonly class Amount
{
	/**
	 * @param int<0, max> $value
	 */
	private function __construct(
		public int $value,
	) {}

	public function __toString(): string
	{
		return (string) $this->value;
	}

	/**
	 * Create an amount from an integer value.
	 *
	 * @throws \InvalidArgumentException if value is negative
	 */
	public static function of(int $value): self
	{
		if ($value < 0) {
			throw new \InvalidArgumentException(
				\sprintf('Amount cannot be negative, got %d', $value),
			);
		}

		return new self($value);
	}

	/**
	 * Create a zero amount.
	 */
	public static function zero(): self
	{
		return new self(0);
	}

	/**
	 * Add another amount to this one.
	 */
	public function add(self $other): self
	{
		/** @var int<0, max> $result */
		$result = $this->value + $other->value;

		return new self($result);
	}

	/**
	 * Subtract another amount from this one.
	 *
	 * @throws \InvalidArgumentException if the result is negative
	 */
	public function subtract(self $other): self
	{
		if ($other->value > $this->value) {
			throw new \InvalidArgumentException(
				\sprintf('Cannot subtract %d from %d (would be negative)', $other->value, $this->value),
			);
		}

		/** @var int<0, max> $result */
		$result = $this->value - $other->value;

		return new self($result);
	}

	/**
	 * Check if this amount is zero.
	 */
	public function isZero(): bool
	{
		return $this->value === 0;
	}

	/**
	 * Compare this amount with another.
	 *
	 * Returns:
	 * - negative integer if this amount is less than the other
	 * - zero if this amount equals the other
	 * - positive integer if this amount is greater than the other
	 */
	public function compare(self $other): int
	{
		return $this->value <=> $other->value;
	}
}
