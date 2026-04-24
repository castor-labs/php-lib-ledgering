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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeOrderedMonotonicTest extends TestCase
{
	#[Test]
	public function it_creates_valid_16_byte_identifiers(): void
	{
		$factory = new TimeOrderedMonotonic(FixedClock::at());

		$id = $factory->create();

		self::assertSame(16, \strlen($id->bytes));
		self::assertFalse($id->isZero());
	}

	#[Test]
	public function it_produces_monotonically_increasing_identifiers_in_same_millisecond(): void
	{
		$factory = new TimeOrderedMonotonic(FixedClock::at());

		$previous = $factory->create();
		for ($i = 0; $i < 100; $i++) {
			$current = $factory->create();

			self::assertGreaterThan(
				$previous->bytes,
				$current->bytes,
				\sprintf('Identifier %d was not greater than its predecessor', $i + 1),
			);

			$previous = $current;
		}
	}

	#[Test]
	public function it_maintains_ordering_across_different_milliseconds(): void
	{
		$clock = FixedClock::at(1_700_000_000);
		$factory = new TimeOrderedMonotonic($clock);

		$first = $factory->create();

		$clock->advance(1);
		$second = $factory->create();

		$clock->advance(60);
		$third = $factory->create();

		self::assertGreaterThan($first->bytes, $second->bytes);
		self::assertGreaterThan($second->bytes, $third->bytes);
	}

	#[Test]
	public function it_preserves_monotonicity_on_clock_regression(): void
	{
		$clock = FixedClock::at(1_700_000_100);
		$factory = new TimeOrderedMonotonic($clock);

		$beforeRegression = $factory->create();

		$clock->setNow(Time\Instant::of(1_700_000_000));

		$afterRegression = $factory->create();

		self::assertGreaterThan($beforeRegression->bytes, $afterRegression->bytes);
	}

	#[Test]
	public function it_embeds_correct_timestamp_in_first_six_bytes(): void
	{
		$timestamp = 1_700_000_000;
		$factory = new TimeOrderedMonotonic(FixedClock::at($timestamp));

		$id = $factory->create();

		$timestampBytes = \substr($id->bytes, 0, 6);
		$unpacked = \unpack('J', "\x00\x00".$timestampBytes);
		\assert($unpacked !== false);
		$embeddedMs = $unpacked[1];

		$expectedMs = $timestamp * 1000;
		self::assertSame($expectedMs, $embeddedMs);
	}

	#[Test]
	public function it_generates_new_random_component_when_time_advances(): void
	{
		$clock = FixedClock::at(1_700_000_000);
		$factory = new TimeOrderedMonotonic($clock);

		$first = $factory->create();
		$firstRandom = \substr($first->bytes, 6);

		$clock->advance(1);

		$second = $factory->create();
		$secondRandom = \substr($second->bytes, 6);

		self::assertNotSame($firstRandom, $secondRandom);
	}

	#[Test]
	public function it_throws_out_of_bounds_on_random_overflow(): void
	{
		$factory = new TimeOrderedMonotonic(FixedClock::at());

		$factory->create();

		$reflection = new \ReflectionClass($factory);
		$property = $reflection->getProperty('lastRandom');
		$property->setValue($factory, "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff");

		$this->expectException(\OutOfBoundsException::class);
		$this->expectExceptionMessage('Random component overflow');

		$factory->create();
	}
}
