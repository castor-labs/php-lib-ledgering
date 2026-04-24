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

use Castor\Ledgering\Time\Clock;
use Castor\Ledgering\Time\DefaultClock;
use Random\Randomizer;

/**
 * Generates time-ordered, monotonically increasing 128-bit identifiers.
 *
 * The binary layout is similar to ULID: a 48-bit millisecond timestamp in the
 * most significant bits followed by an 80-bit random component. This ensures
 * that identifiers are lexicographically sortable by creation time.
 *
 * ```
 * ┌──────────────────────────┬──────────────────────────────────────┐
 * │   48-bit timestamp (ms)  │        80-bit random/counter         │
 * │     (6 bytes, BE)        │          (10 bytes)                  │
 * └──────────────────────────┴──────────────────────────────────────┘
 * ```
 *
 * Within the same millisecond, the random component is incremented by one
 * to guarantee strict monotonic ordering. If the clock regresses (e.g. NTP
 * adjustment), the last observed timestamp is reused and the random component
 * is incremented, preserving monotonicity.
 *
 * This ordering is the preferred strategy for generating identifiers in this
 * library because it:
 * - Minimises B-tree page splits in database indexes
 * - Enables efficient cursor-based pagination using the identifier itself
 * - Preserves rough insertion-time ordering for debugging and auditing
 */
final class TimeOrderedMonotonic implements IdentifierFactory
{
	private const int RANDOM_BYTES = 10;

	private const string MAX_RANDOM = "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff";

	private int $lastTimestampMs = 0;

	private string $lastRandom = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

	public function __construct(
		private readonly Clock $clock = new DefaultClock(),
		private readonly Randomizer $randomizer = new Randomizer(),
	) {}

	#[\Override]
	public function create(): Identifier
	{
		$timestampMs = $this->clock->now()->toMilliseconds();

		if ($timestampMs <= $this->lastTimestampMs) {
			$this->lastRandom = self::incrementBytes($this->lastRandom);
			$timestampMs = $this->lastTimestampMs;
		} else {
			$this->lastTimestampMs = $timestampMs;
			$this->lastRandom = $this->randomizer->getBytes(self::RANDOM_BYTES);
		}

		$timestampBytes = \substr(\pack('J', $timestampMs), 2, 6);

		return Identifier::fromBytes($timestampBytes.$this->lastRandom);
	}

	/**
	 * Increment a byte string by one, treating it as a big-endian unsigned integer.
	 *
	 * @throws \OutOfBoundsException if all bytes are 0xFF (overflow). This is
	 *                               extraordinarily rare: it requires 2^80
	 *                               (~1.2 * 10^24) identifiers to be generated
	 *                               within a single millisecond.
	 */
	private static function incrementBytes(string $bytes): string
	{
		if ($bytes === self::MAX_RANDOM) {
			throw new \OutOfBoundsException(
				'Random component overflow: cannot generate more identifiers in this millisecond',
			);
		}

		for ($i = self::RANDOM_BYTES - 1; $i >= 0; $i--) {
			$byte = \ord($bytes[$i]);
			if ($byte < 0xFF) {
				$bytes[$i] = \chr($byte + 1);

				return $bytes;
			}
			$bytes[$i] = "\x00";
		}

		throw new \OutOfBoundsException('Random component overflow');
	}
}
