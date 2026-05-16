<?php

declare(strict_types=1);

namespace Castor\Ledgering;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CodeTest extends TestCase
{
	#[Test]
	#[TestWith([1])]
	#[TestWith([100])]
	#[TestWith([999999])]
	public function it_creates_code_from_positive_value(int $value): void
	{
		$code = Code::of($value);

		self::assertSame($value, $code->value);
	}

	#[Test]
	#[TestWith([0])]
	#[TestWith([-1])]
	#[TestWith([-100])]
	public function it_throws_on_non_positive_value(int $value): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Code must be positive (non-zero)');

		Code::of($value);
	}

	#[Test]
	public function it_checks_equality(): void
	{
		$a = Code::of(100);
		$b = Code::of(100);
		$c = Code::of(200);

		self::assertTrue($a->equals($b));
		self::assertFalse($a->equals($c));
	}

	#[Test]
	public function it_converts_to_string(): void
	{
		$code = Code::of(12345);

		self::assertSame('12345', (string) $code);
	}
}
