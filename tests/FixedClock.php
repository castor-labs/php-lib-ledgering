<?php

/**
 * This file is part of the Castor Ledgering Library.
 *
 * (c) Matías Navarro-Carter <contact@mnavarro.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Castor\Ledgering;

use Castor\Ledgering\Time\Clock;
use Castor\Ledgering\Time\Instant;

/**
 * A fixed clock for testing that allows manual time advancement.
 *
 * This clock starts at a fixed point in time (2024-01-01 00:00:00 UTC by default)
 * and can be advanced manually using the advance() method.
 */
final class FixedClock implements Clock
{
	/**
	 * @param int $time Initial Unix timestamp in seconds
	 */
	private function __construct(
		private int $time = 1704067200,
	) {}

	/**
	 * Creates a new FixedClock starting at the given timestamp.
	 *
	 * @param int $timestamp Unix timestamp in seconds (default: 2024-01-01 00:00:00 UTC)
	 */
	public static function at(int $timestamp = 1704067200): self
	{
		return new self($timestamp);
	}

	#[\Override]
	public function now(): Instant
	{
		return Instant::of($this->time);
	}

	/**
	 * Advances the clock by the given number of seconds.
	 *
	 * @param int $seconds Number of seconds to advance
	 */
	public function advance(int $seconds): void
	{
		$this->time += $seconds;
	}

	/**
	 * Sets the clock to a specific instant.
	 *
	 * @param Instant $instant The instant to set the clock to
	 */
	public function setNow(Instant $instant): void
	{
		$this->time = $instant->seconds;
	}
}
