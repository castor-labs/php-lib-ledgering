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

namespace Castor\Ledgering\Time;

/**
 * Represents a point in time as seconds and nanoseconds since Unix epoch.
 *
 * Immutable and timezone-independent.
 */
final readonly class Instant
{
	private const int NANOS_PER_SECOND = 1_000_000_000;

	private function __construct(
		public int $seconds,
		public int $nano,
	) {}

	public function __toString(): string
	{
		return $this->toUtcIsoString();
	}

	/**
	 * Create an instant from seconds and nanoseconds since Unix epoch.
	 *
	 * Normalizes nanoseconds to be within [0, 999_999_999].
	 */
	public static function of(int $seconds, int $nano = 0): self
	{
		// Normalize nanoseconds
		$extraSeconds = \intdiv($nano, self::NANOS_PER_SECOND);
		$normalizedNano = $nano % self::NANOS_PER_SECOND;

		// Handle negative nanoseconds
		if ($normalizedNano < 0) {
			$normalizedNano += self::NANOS_PER_SECOND;
			$extraSeconds--;
		}

		return new self($seconds + $extraSeconds, $normalizedNano);
	}

	/**
	 * Create an instant representing the current time.
	 */
	public static function now(): self
	{
		$microtime = \microtime(true);
		$seconds = (int) \floor($microtime);
		$nano = (int) (($microtime - (float) $seconds) * (float) self::NANOS_PER_SECOND);

		return new self($seconds, $nano);
	}

	/**
	 * Convert to UTC ISO 8601 string format.
	 *
	 * Format: YYYY-MM-DDTHH:MM:SS.fffffffffZ
	 */
	public function toUtcIsoString(): string
	{
		$formatted = \gmdate('Y-m-d\TH:i:s', $this->seconds);

		// Add nanoseconds (9 digits)
		$nanoStr = \str_pad((string) $this->nano, 9, '0', \STR_PAD_LEFT);

		return $formatted.'.'.$nanoStr.'Z';
	}

	/**
	 * Compare this instant with another.
	 *
	 * Returns:
	 * - negative if this instant is before the other
	 * - zero if instants are equal
	 * - positive if this instant is after the other
	 */
	public function compare(self $other): int
	{
		$secondsDiff = $this->seconds <=> $other->seconds;
		if ($secondsDiff !== 0) {
			return $secondsDiff;
		}

		return $this->nano <=> $other->nano;
	}

	/**
	 * Check if this instant equals another.
	 */
	public function equals(self $other): bool
	{
		return $this->seconds === $other->seconds && $this->nano === $other->nano;
	}

	/**
	 * Convert this instant to milliseconds since the Unix epoch.
	 *
	 * Sub-millisecond precision is truncated (not rounded).
	 */
	public function toMilliseconds(): int
	{
		return ($this->seconds * 1000) + \intdiv($this->nano, 1_000_000);
	}
}
