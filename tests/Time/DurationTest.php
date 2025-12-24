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

namespace Castor\Ledgering\Tests\Time;

use Castor\Ledgering\Time\Duration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
	#[Test]
	#[TestWith([0])]
	#[TestWith([60])]
	#[TestWith([3600])]
	public function it_creates_from_seconds(int $seconds): void
	{
		$duration = Duration::ofSeconds($seconds);

		self::assertSame($seconds, $duration->seconds);
	}

	#[Test]
	public function it_throws_on_negative_seconds(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Duration cannot be negative');

		Duration::ofSeconds(-1);
	}

	#[Test]
	public function it_creates_zero_duration(): void
	{
		$duration = Duration::zero();

		self::assertSame(0, $duration->seconds);
		self::assertTrue($duration->isZero());
	}

	#[Test]
	public function it_creates_from_components(): void
	{
		$duration = Duration::of(hours: 1, minutes: 30, seconds: 45);

		self::assertSame(5445, $duration->seconds); // 3600 + 1800 + 45
	}

	#[Test]
	public function it_creates_from_days(): void
	{
		$duration = Duration::ofDays(2);

		self::assertSame(172800, $duration->seconds); // 2 * 86400
	}

	#[Test]
	public function it_creates_from_hours(): void
	{
		$duration = Duration::ofHours(3);

		self::assertSame(10800, $duration->seconds); // 3 * 3600
	}

	#[Test]
	public function it_creates_from_minutes(): void
	{
		$duration = Duration::ofMinutes(45);

		self::assertSame(2700, $duration->seconds); // 45 * 60
	}

	#[Test]
	public function it_throws_on_negative_components(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Duration components cannot be negative');

		Duration::of(hours: -1);
	}

	#[Test]
	public function it_adds_durations(): void
	{
		$a = Duration::ofSeconds(100);
		$b = Duration::ofSeconds(50);

		$result = $a->add($b);

		self::assertSame(150, $result->seconds);
	}

	#[Test]
	public function it_checks_if_zero(): void
	{
		self::assertTrue(Duration::zero()->isZero());
		self::assertFalse(Duration::ofSeconds(1)->isZero());
	}

	#[Test]
	public function it_compares_durations(): void
	{
		$shorter = Duration::ofSeconds(100);
		$longer = Duration::ofSeconds(200);
		$same = Duration::ofSeconds(100);

		self::assertLessThan(0, $shorter->compare($longer));
		self::assertGreaterThan(0, $longer->compare($shorter));
		self::assertSame(0, $shorter->compare($same));
	}

	#[Test]
	public function it_checks_equality(): void
	{
		$a = Duration::ofSeconds(100);
		$b = Duration::ofSeconds(100);
		$c = Duration::ofSeconds(200);

		self::assertTrue($a->equals($b));
		self::assertFalse($a->equals($c));
	}

	#[Test]
	public function it_converts_to_string(): void
	{
		$duration = Duration::ofSeconds(3600);

		self::assertSame('3600s', (string) $duration);
	}
}
