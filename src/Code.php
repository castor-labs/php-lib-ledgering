<?php

declare(strict_types=1);

namespace Castor\Ledgering;

/**
 * Represents a numeric code for ledgers, accounts, or transfers.
 *
 * Codes must be positive (non-zero) integers.
 */
final readonly class Code implements \Stringable
{
	private function __construct(
		public int $value,
	) {}

	public function __toString(): string
	{
		return (string) $this->value;
	}

	/**
	 * Create a code from an integer value.
	 *
	 * @throws \InvalidArgumentException if the value is zero or negative
	 */
	public static function of(int $value): self
	{
		if ($value <= 0) {
			throw new \InvalidArgumentException(\sprintf('Code must be positive (non-zero), got %d', $value));
		}

		return new self($value);
	}

	/**
	 * Check if this code equals another.
	 */
	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}
}
