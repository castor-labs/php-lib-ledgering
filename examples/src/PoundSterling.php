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

namespace Castor\Ledgering\Example;

/**
 * Represents a monetary amount in Pound Sterling (GBP).
 *
 * Internally stores the amount in pence (smallest unit) to avoid floating-point errors.
 * Provides arithmetic operations and formatting capabilities.
 */
final readonly class PoundSterling
{
	/**
	 * @param int<0, max> $pence The amount in pence (must be non-negative)
	 */
	private function __construct(
		public int $pence,
	) {}

	public function __toString(): string
	{
		return $this->format();
	}

	/**
	 * Create an amount from pence.
	 *
	 * @throws \InvalidArgumentException if pence is negative
	 */
	public static function ofPence(int $pence): self
	{
		if ($pence < 0) {
			throw new \InvalidArgumentException(
				\sprintf('Amount cannot be negative, got %d pence', $pence),
			);
		}

		return new self($pence);
	}

	/**
	 * Create an amount from pounds (will be converted to pence).
	 *
	 * @param float $pounds The amount in pounds (e.g., 10.50 for £10.50)
	 *
	 * @throws \InvalidArgumentException if pounds is negative
	 */
	public static function ofPounds(float $pounds): self
	{
		if ($pounds < 0) {
			throw new \InvalidArgumentException(
				\sprintf('Amount cannot be negative, got %.2f pounds', $pounds),
			);
		}

		$pence = (int) \round($pounds * 100);

		return new self($pence);
	}

	/**
	 * Parse a monetary amount from a string.
	 *
	 * Accepts formats like:
	 * - "1000" (as pounds)
	 * - "10.50"
	 * - "£10.50"
	 * - "1,000.50"
	 *
	 * @throws \InvalidArgumentException if the string cannot be parsed
	 */
	public static function parse(string $amount): self
	{
		// Remove currency symbols and commas
		$cleaned = \str_replace(['£', '$', ','], '', \trim($amount));

		if ($cleaned === '') {
			throw new \InvalidArgumentException('Amount string cannot be empty');
		}

		$float = \filter_var($cleaned, \FILTER_VALIDATE_FLOAT);
		if ($float === false) {
			throw new \InvalidArgumentException(
				\sprintf('Invalid amount format: "%s"', $amount),
			);
		}

		return self::ofPounds($float);
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
		return new self($this->pence + $other->pence);
	}

	/**
	 * Subtract another amount from this one.
	 *
	 * @throws \InvalidArgumentException if the result would be negative
	 */
	public function subtract(self $other): self
	{
		$result = $this->pence - $other->pence;

		if ($result < 0) {
			throw new \InvalidArgumentException(
				\sprintf(
					'Cannot subtract %s from %s (result would be negative)',
					$other->format(),
					$this->format(),
				),
			);
		}

		return new self($result);
	}

	/**
	 * Multiply this amount by a factor.
	 *
	 * @param int|float $factor The multiplication factor
	 *
	 * @throws \InvalidArgumentException if the result would be negative
	 */
	public function multiply(int|float $factor): self
	{
		$result = (int) \round($this->pence * $factor);

		if ($result < 0) {
			throw new \InvalidArgumentException(
				\sprintf('Cannot multiply by negative factor: %s', $factor),
			);
		}

		return new self($result);
	}

	/**
	 * Format the amount as a string (e.g., "£10.50").
	 */
	public function format(): string
	{
		return '£'.\number_format($this->pence / 100, 2);
	}

	/**
	 * Check if this amount is zero.
	 */
	public function isZero(): bool
	{
		return $this->pence === 0;
	}

	/**
	 * Check if this amount equals another.
	 */
	public function equals(self $other): bool
	{
		return $this->pence === $other->pence;
	}

	/**
	 * Compare this amount to another.
	 *
	 * @return int -1 if less than, 0 if equal, 1 if greater than
	 */
	public function compare(self $other): int
	{
		return $this->pence <=> $other->pence;
	}
}
