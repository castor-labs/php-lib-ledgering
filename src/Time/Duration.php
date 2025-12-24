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

namespace Castor\Ledgering\Time;

/**
 * Represents a duration as a number of seconds.
 *
 * Immutable and always non-negative.
 */
final readonly class Duration
{
	private const int SECONDS_PER_MINUTE = 60;

	private const int SECONDS_PER_HOUR = 3600;

	private const int SECONDS_PER_DAY = 86400;

	private function __construct(
		public int $seconds,
	) {}

	public function __toString(): string
	{
		return $this->seconds.'s';
	}

	/**
	 * Create a duration from a number of seconds.
	 *
	 * @throws \InvalidArgumentException if seconds is negative
	 */
	public static function ofSeconds(int $seconds): self
	{
		if ($seconds < 0) {
			throw new \InvalidArgumentException(
				\sprintf('Duration cannot be negative, got %d seconds', $seconds),
			);
		}

		return new self($seconds);
	}

	/**
	 * Create a zero duration.
	 */
	public static function zero(): self
	{
		return new self(0);
	}

	/**
	 * Create a duration from hours, minutes, and seconds.
	 *
	 * All parameters are optional and default to 0.
	 *
	 * @throws \InvalidArgumentException if any parameter is negative
	 */
	public static function of(
		int $hours = 0,
		int $minutes = 0,
		int $seconds = 0,
		int $days = 0,
	): self {
		if ($hours < 0 || $minutes < 0 || $seconds < 0 || $days < 0) {
			throw new \InvalidArgumentException('Duration components cannot be negative');
		}

		$totalSeconds =
			($days * self::SECONDS_PER_DAY) +
			($hours * self::SECONDS_PER_HOUR) +
			($minutes * self::SECONDS_PER_MINUTE) +
			$seconds;

		return new self($totalSeconds);
	}

	/**
	 * Create a duration from days.
	 */
	public static function ofDays(int $days): self
	{
		return self::of(days: $days);
	}

	/**
	 * Create a duration from hours.
	 */
	public static function ofHours(int $hours): self
	{
		return self::of(hours: $hours);
	}

	/**
	 * Create a duration from minutes.
	 */
	public static function ofMinutes(int $minutes): self
	{
		return self::of(minutes: $minutes);
	}

	/**
	 * Add another duration to this one.
	 *
	 * @throws \OverflowException if result would overflow
	 */
	public function add(self $other): self
	{
		$result = $this->seconds + $other->seconds;

		if ($result < 0) {
			throw new \OverflowException('Duration addition would overflow');
		}

		return new self($result);
	}

	/**
	 * Check if this duration is zero.
	 */
	public function isZero(): bool
	{
		return $this->seconds === 0;
	}

	/**
	 * Compare this duration with another.
	 *
	 * Returns:
	 * - negative if this duration is shorter than the other
	 * - zero if durations are equal
	 * - positive if this duration is longer than the other
	 */
	public function compare(self $other): int
	{
		return $this->seconds <=> $other->seconds;
	}

	/**
	 * Check if this duration equals another.
	 */
	public function equals(self $other): bool
	{
		return $this->seconds === $other->seconds;
	}
}
