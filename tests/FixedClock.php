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
	private int $time;

	/**
	 * @param int $timestamp Initial Unix timestamp in seconds
	 */
	private function __construct(int $timestamp = 1704067200)
	{
		$this->time = $timestamp;
	}

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
}
