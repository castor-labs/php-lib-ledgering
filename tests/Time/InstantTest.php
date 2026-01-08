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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantTest extends TestCase
{
	#[Test]
	public function it_creates_from_seconds(): void
	{
		$instant = Instant::of(1234567890);

		self::assertSame(1234567890, $instant->seconds);
		self::assertSame(0, $instant->nano);
	}

	#[Test]
	public function it_creates_from_seconds_and_nanos(): void
	{
		$instant = Instant::of(1234567890, 123456789);

		self::assertSame(1234567890, $instant->seconds);
		self::assertSame(123456789, $instant->nano);
	}

	#[Test]
	public function it_normalizes_nanoseconds_overflow(): void
	{
		$instant = Instant::of(100, 1_500_000_000);

		self::assertSame(101, $instant->seconds);
		self::assertSame(500_000_000, $instant->nano);
	}

	#[Test]
	public function it_normalizes_negative_nanoseconds(): void
	{
		$instant = Instant::of(100, -500_000_000);

		self::assertSame(99, $instant->seconds);
		self::assertSame(500_000_000, $instant->nano);
	}

	#[Test]
	public function it_creates_current_instant(): void
	{
		$before = \time();
		$instant = Instant::now();
		$after = \time();

		self::assertGreaterThanOrEqual($before, $instant->seconds);
		self::assertLessThanOrEqual($after, $instant->seconds);
		self::assertGreaterThanOrEqual(0, $instant->nano);
		self::assertLessThan(1_000_000_000, $instant->nano);
	}

	#[Test]
	public function it_converts_to_utc_iso_string(): void
	{
		$instant = Instant::of(1234567890, 123456789);

		$result = $instant->toUtcIsoString();

		self::assertSame('2009-02-13T23:31:30.123456789Z', $result);
	}

	#[Test]
	public function it_pads_nanoseconds_in_iso_string(): void
	{
		$instant = Instant::of(1234567890, 1);

		$result = $instant->toUtcIsoString();

		self::assertSame('2009-02-13T23:31:30.000000001Z', $result);
	}

	#[Test]
	public function it_compares_instants(): void
	{
		$earlier = Instant::of(100, 500_000_000);
		$later = Instant::of(200, 500_000_000);
		$same = Instant::of(100, 500_000_000);

		self::assertLessThan(0, $earlier->compare($later));
		self::assertGreaterThan(0, $later->compare($earlier));
		self::assertSame(0, $earlier->compare($same));
	}

	#[Test]
	public function it_compares_by_nanoseconds_when_seconds_equal(): void
	{
		$earlier = Instant::of(100, 100);
		$later = Instant::of(100, 200);

		self::assertLessThan(0, $earlier->compare($later));
		self::assertGreaterThan(0, $later->compare($earlier));
	}

	#[Test]
	public function it_checks_equality(): void
	{
		$a = Instant::of(100, 500_000_000);
		$b = Instant::of(100, 500_000_000);
		$c = Instant::of(100, 600_000_000);
		$d = Instant::of(200, 500_000_000);

		self::assertTrue($a->equals($b));
		self::assertFalse($a->equals($c));
		self::assertFalse($a->equals($d));
	}

	#[Test]
	public function it_converts_to_string(): void
	{
		$instant = Instant::of(1234567890, 123456789);

		self::assertSame('2009-02-13T23:31:30.123456789Z', (string) $instant);
	}
}
