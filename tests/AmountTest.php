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

namespace Castor\Ledgering;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
	#[Test]
	#[TestWith([0])]
	#[TestWith([100])]
	#[TestWith([999999])]
	public function it_creates_amount_from_valid_value(int $value): void
	{
		$amount = Amount::of($value);

		self::assertSame($value, $amount->value);
	}

	#[Test]
	public function it_throws_on_negative_value(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Amount cannot be negative');

		Amount::of(-1);
	}

	#[Test]
	public function it_creates_zero_amount(): void
	{
		$amount = Amount::zero();

		self::assertSame(0, $amount->value);
		self::assertTrue($amount->isZero());
	}

	#[Test]
	public function it_adds_amounts(): void
	{
		$a = Amount::of(100);
		$b = Amount::of(50);

		$result = $a->add($b);

		self::assertSame(150, $result->value);
	}

	#[Test]
	public function it_subtracts_amounts(): void
	{
		$a = Amount::of(100);
		$b = Amount::of(30);

		$result = $a->subtract($b);

		self::assertSame(70, $result->value);
	}

	#[Test]
	public function it_throws_when_subtraction_would_be_negative(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('would be negative');

		$a = Amount::of(50);
		$b = Amount::of(100);

		$a->subtract($b);
	}

	#[Test]
	#[TestWith([0, 0, 0])]
	#[TestWith([100, 50, 1])]
	#[TestWith([50, 100, -1])]
	#[TestWith([100, 100, 0])]
	public function it_compares_amounts(int $aValue, int $bValue, int $expected): void
	{
		$a = Amount::of($aValue);
		$b = Amount::of($bValue);

		$result = $a->compare($b);

		if ($expected > 0) {
			self::assertGreaterThan(0, $result);
		} elseif ($expected < 0) {
			self::assertLessThan(0, $result);
		} else {
			self::assertSame(0, $result);
		}
	}

	#[Test]
	public function it_converts_to_string(): void
	{
		$amount = Amount::of(12345);

		self::assertSame('12345', (string) $amount);
	}

	#[Test]
	public function it_checks_if_zero(): void
	{
		self::assertTrue(Amount::zero()->isZero());
		self::assertFalse(Amount::of(1)->isZero());
	}
}
