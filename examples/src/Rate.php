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

use Decimal\Decimal;

/**
 * Represents a rate (e.g., APR, interest rate) using arbitrary precision decimals.
 *
 * Internally uses ext-decimal for precise decimal arithmetic without floating-point errors.
 */
final class Rate
{
	private Decimal $value;

	private function __construct(Decimal $value)
	{
		if ($value->isNegative()) {
			throw new \InvalidArgumentException('Rate cannot be negative');
		}
		$this->value = $value;
	}

	/**
	 * Create a rate from a decimal value (e.g., 0.15 for 15%)
	 */
	public static function of(string|float|int $value): self
	{
		return new self(new Decimal((string) $value));
	}

	/**
	 * Create a rate from a percentage (e.g., 15 for 15%)
	 */
	public static function fromPercentage(string|float|int $percentage): self
	{
		$decimal = new Decimal((string) $percentage);

		return new self($decimal->div('100'));
	}

	/**
	 * Parse a rate from a string (supports both decimal and percentage formats)
	 * Examples: "0.15", "15%"
	 */
	public static function parse(string $input): self
	{
		$input = \trim($input);

		if (\str_ends_with($input, '%')) {
			$percentage = \rtrim($input, '%');

			return self::fromPercentage($percentage);
		}

		return self::of($input);
	}

	/**
	 * Create a zero rate
	 */
	public static function zero(): self
	{
		return new self(new Decimal('0'));
	}

	/**
	 * Get the rate as a decimal value (e.g., 0.15 for 15%)
	 */
	public function toDecimal(): Decimal
	{
		return $this->value;
	}

	/**
	 * Get the rate as a float (use with caution - may lose precision)
	 */
	public function toFloat(): float
	{
		return (float) $this->value->toString();
	}

	/**
	 * Get the rate as a percentage decimal (e.g., 15 for 15%)
	 */
	public function toPercentage(): Decimal
	{
		return $this->value->mul('100');
	}

	/**
	 * Multiply this rate by a number
	 */
	public function multiply(string|float|int $factor): self
	{
		$result = $this->value->mul((string) $factor);

		return new self($result);
	}

	/**
	 * Divide this rate by a number
	 */
	public function divide(string|float|int $divisor): self
	{
		$result = $this->value->div((string) $divisor);

		return new self($result);
	}

	/**
	 * Add another rate to this one
	 */
	public function add(Rate $other): self
	{
		$result = $this->value->add($other->value);

		return new self($result);
	}

	/**
	 * Subtract another rate from this one
	 */
	public function subtract(Rate $other): self
	{
		$result = $this->value->sub($other->value);

		return new self($result);
	}

	/**
	 * Check if this rate is zero
	 */
	public function isZero(): bool
	{
		return $this->value->isZero();
	}

	/**
	 * Check if two rates are equal
	 */
	public function equals(Rate $other): bool
	{
		return $this->value->equals($other->value);
	}

	/**
	 * Format as a percentage string (e.g., "15%")
	 */
	public function formatAsPercentage(int $decimals = 2): string
	{
		return $this->toPercentage()->toFixed($decimals).'%';
	}

	/**
	 * Format as a decimal string (e.g., "0.15")
	 */
	public function format(int $decimals = 6): string
	{
		return $this->value->toFixed($decimals);
	}
}
