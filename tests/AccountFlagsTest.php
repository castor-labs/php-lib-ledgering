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
use PHPUnit\Framework\TestCase;

final class AccountFlagsTest extends TestCase
{
	#[Test]
	public function it_creates_none_flags(): void
	{
		$flags = AccountFlags::none();

		self::assertSame(0, $flags->toInt());
	}

	#[Test]
	public function it_creates_from_integer(): void
	{
		$flags = AccountFlags::of(AccountFlags::HISTORY);

		self::assertTrue($flags->hasHistory());
	}

	#[Test]
	public function it_checks_debits_must_not_exceed_credits(): void
	{
		$flags = AccountFlags::of(AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS);

		self::assertTrue($flags->debitsMusNotExceedCredits());
		self::assertFalse($flags->creditsMusNotExceedDebits());
	}

	#[Test]
	public function it_checks_credits_must_not_exceed_debits(): void
	{
		$flags = AccountFlags::of(AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS);

		self::assertTrue($flags->creditsMusNotExceedDebits());
		self::assertFalse($flags->debitsMusNotExceedCredits());
	}

	#[Test]
	public function it_throws_on_mutually_exclusive_flags(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('mutually exclusive');

		AccountFlags::of(
			AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS | AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
		);
	}

	#[Test]
	public function it_checks_history_flag(): void
	{
		$flags = AccountFlags::of(AccountFlags::HISTORY);

		self::assertTrue($flags->hasHistory());
	}

	#[Test]
	public function it_checks_closed_flag(): void
	{
		$flags = AccountFlags::of(AccountFlags::CLOSED);

		self::assertTrue($flags->isClosed());
	}

	#[Test]
	public function it_adds_flags(): void
	{
		$flags = AccountFlags::none()
			->with(AccountFlags::HISTORY)
			->with(AccountFlags::CLOSED);

		self::assertTrue($flags->hasHistory());
		self::assertTrue($flags->isClosed());
	}

	#[Test]
	public function it_removes_flags(): void
	{
		$flags = AccountFlags::of(AccountFlags::HISTORY | AccountFlags::CLOSED)
			->without(AccountFlags::HISTORY);

		self::assertFalse($flags->hasHistory());
		self::assertTrue($flags->isClosed());
	}

	#[Test]
	public function it_checks_equality(): void
	{
		$a = AccountFlags::of(AccountFlags::HISTORY);
		$b = AccountFlags::of(AccountFlags::HISTORY);
		$c = AccountFlags::of(AccountFlags::CLOSED);

		self::assertTrue($a->equals($b));
		self::assertFalse($a->equals($c));
	}

	#[Test]
	public function it_converts_to_string(): void
	{
		$flags = AccountFlags::of(5);

		self::assertSame('5', (string) $flags);
	}
}
