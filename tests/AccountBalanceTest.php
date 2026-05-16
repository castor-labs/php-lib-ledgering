<?php

declare(strict_types=1);

namespace Castor\Ledgering;

use Castor\Ledgering\Time\Instant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountBalanceTest extends TestCase
{
	#[Test]
	public function it_creates_account_balance(): void
	{
		$accountId = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$balance = new Balance(
			debitsPosted: Amount::of(1000),
			creditsPosted: Amount::of(500),
			debitsPending: Amount::of(200),
			creditsPending: Amount::of(100),
		);
		$timestamp = Instant::of(1234567890);

		$accountBalance = new AccountBalance($accountId, $balance, $timestamp);

		self::assertTrue($accountBalance->accountId->equals($accountId));
		self::assertSame(1000, $accountBalance->balance->debitsPosted->value);
		self::assertSame(500, $accountBalance->balance->creditsPosted->value);
		self::assertSame(200, $accountBalance->balance->debitsPending->value);
		self::assertSame(100, $accountBalance->balance->creditsPending->value);
		self::assertSame(1234567890, $accountBalance->timestamp->seconds);
	}

	#[Test]
	public function it_creates_account_balance_with_zero_balance(): void
	{
		$accountId = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$balance = Balance::zero();
		$timestamp = Instant::now();

		$accountBalance = new AccountBalance($accountId, $balance, $timestamp);

		self::assertTrue($accountBalance->accountId->equals($accountId));
		self::assertSame(0, $accountBalance->balance->debitsPosted->value);
		self::assertSame(0, $accountBalance->balance->creditsPosted->value);
		self::assertSame(0, $accountBalance->balance->debitsPending->value);
		self::assertSame(0, $accountBalance->balance->creditsPending->value);
	}
}
