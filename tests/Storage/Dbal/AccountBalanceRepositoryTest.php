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

namespace Castor\Ledgering\Storage\Dbal;

use Castor\Ledgering\AccountBalance;
use Castor\Ledgering\Amount;
use Castor\Ledgering\Balance;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Infra\Database;
use Castor\Ledgering\Storage\InvalidResult;
use Castor\Ledgering\Time\Instant;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class AccountBalanceRepositoryTest extends TestCase
{
	#[Test]
	public function it_writes_and_reads_account_balance(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$balance = new AccountBalance(
			accountId: $accountId,
			balance: new Balance(
				debitsPosted: Amount::of(1000),
				creditsPosted: Amount::of(500),
				debitsPending: Amount::of(200),
				creditsPending: Amount::of(100),
			),
			timestamp: Instant::of(1234567890, 123456789),
		);

		$repository->write($balance);

		$retrieved = $repository->ofAccountId($accountId)->first();

		self::assertNotNull($retrieved);
		self::assertTrue($retrieved->accountId->equals($accountId));
		self::assertSame(1000, $retrieved->balance->debitsPosted->value);
		self::assertSame(500, $retrieved->balance->creditsPosted->value);
		self::assertSame(200, $retrieved->balance->debitsPending->value);
		self::assertSame(100, $retrieved->balance->creditsPending->value);
		self::assertSame(1234567890, $retrieved->timestamp->seconds);
		self::assertSame(123456789, $retrieved->timestamp->nano);
	}

	#[Test]
	public function it_appends_multiple_balances_for_same_account(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();

		$balance1 = new AccountBalance(
			accountId: $accountId,
			balance: Balance::zero(),
			timestamp: Instant::of(1000),
		);

		$balance2 = new AccountBalance(
			accountId: $accountId,
			balance: new Balance(
				debitsPosted: Amount::of(1000),
				creditsPosted: Amount::of(500),
				debitsPending: Amount::of(0),
				creditsPending: Amount::of(0),
			),
			timestamp: Instant::of(2000),
		);

		$repository->write($balance1);
		$repository->write($balance2);

		$balances = $repository->ofAccountId($accountId)->toList();

		self::assertCount(2, $balances);
		self::assertSame(1000, $balances[0]->timestamp->seconds);
		self::assertSame(2000, $balances[1]->timestamp->seconds);
	}

	#[Test]
	public function it_filters_by_account_id(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId1 = Identifier::random();
		$accountId2 = Identifier::random();

		$balance1 = new AccountBalance($accountId1, Balance::zero(), Instant::of(1000));
		$balance2 = new AccountBalance($accountId2, Balance::zero(), Instant::of(2000));

		$repository->write($balance1);
		$repository->write($balance2);

		$filtered = $repository->ofAccountId($accountId1)->toList();

		self::assertCount(1, $filtered);
		self::assertTrue($filtered[0]->accountId->equals($accountId1));
	}

	#[Test]
	public function it_filters_by_multiple_account_ids(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId1 = Identifier::random();
		$accountId2 = Identifier::random();
		$accountId3 = Identifier::random();

		$repository->write(new AccountBalance($accountId1, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId2, Balance::zero(), Instant::of(2000)));
		$repository->write(new AccountBalance($accountId3, Balance::zero(), Instant::of(3000)));

		$filtered = $repository->ofAccountId($accountId1, $accountId3)->toList();

		self::assertCount(2, $filtered);
	}

	#[Test]
	public function it_counts_balances(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$scoped = $repository->ofAccountId($accountId);

		self::assertSame(0, $scoped->count());

		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));
		self::assertSame(1, $scoped->count());

		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(2000)));
		self::assertSame(2, $scoped->count());
	}

	#[Test]
	public function it_returns_first_balance(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId1 = Identifier::random();
		$accountId2 = Identifier::random();

		$balance1 = new AccountBalance($accountId1, Balance::zero(), Instant::of(1000));
		$balance2 = new AccountBalance($accountId2, Balance::zero(), Instant::of(2000));

		$repository->write($balance1);
		$repository->write($balance2);

		$first = $repository->ofAccountId($accountId1, $accountId2)->first();

		self::assertNotNull($first);
		self::assertTrue($first->accountId->equals($accountId1));
	}

	#[Test]
	public function it_returns_null_when_first_on_empty_result(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();

		$first = $repository->ofAccountId($accountId)->first();

		self::assertNull($first);
	}

	#[Test]
	public function it_returns_one_balance(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$balance = new AccountBalance($accountId, Balance::zero(), Instant::of(1000));
		$repository->write($balance);

		$one = $repository->ofAccountId($accountId)->one();

		self::assertTrue($one->accountId->equals($accountId));
	}

	#[Test]
	public function it_throws_when_one_on_empty_result(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Reader is empty');

		$repository->ofAccountId($accountId)->one();
	}

	#[Test]
	public function it_throws_when_one_on_multiple_balances(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(2000)));

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Expected exactly one item, found 2');

		$repository->ofAccountId($accountId)->one();
	}

	#[Test]
	public function it_picks_exact_number_of_balances(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(2000)));

		$balances = $repository->ofAccountId($accountId)->pick(2);

		self::assertCount(2, $balances);
	}

	#[Test]
	public function it_throws_when_pick_count_mismatch(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Expected exactly 2 items, found 1');

		$repository->ofAccountId($accountId)->pick(2);
	}

	#[Test]
	public function it_slices_balances_with_limit(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(2000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(3000)));

		/** @var array<AccountBalance> $sliced */
		$sliced = $repository->ofAccountId($accountId)->slice(0, 2)->toList();

		self::assertCount(2, $sliced);
		self::assertSame(1000, $sliced[0]->timestamp->seconds);
		self::assertSame(2000, $sliced[1]->timestamp->seconds);
	}

	#[Test]
	public function it_slices_balances_with_limit_of_one(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(2000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(3000)));

		/** @var array<AccountBalance> $sliced */
		$sliced = $repository->ofAccountId($accountId)->slice(0, 1)->toList();

		self::assertCount(1, $sliced);
		self::assertSame(1000, $sliced[0]->timestamp->seconds);
	}

	#[Test]
	public function it_converts_to_iterator(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId = Identifier::random();
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId, Balance::zero(), Instant::of(2000)));

		$iterator = $repository->ofAccountId($accountId)->toIterator();

		self::assertInstanceOf(\Iterator::class, $iterator);
		self::assertCount(2, \iterator_to_array($iterator));
	}

	#[Test]
	public function it_converts_to_map(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId1 = Identifier::random();
		$accountId2 = Identifier::random();

		$repository->write(new AccountBalance($accountId1, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId2, Balance::zero(), Instant::of(2000)));

		$map = $repository->ofAccountId($accountId1, $accountId2)->toMap(static fn(AccountBalance $b) => $b->accountId->toHex());

		self::assertCount(2, $map);
		self::assertArrayHasKey($accountId1->toHex(), $map);
		self::assertArrayHasKey($accountId2->toHex(), $map);
	}

	#[Test]
	public function it_converts_to_list_map(): void
	{
		$connection = Database::connection();
		$repository = new AccountBalanceRepository($connection);

		$accountId1 = Identifier::random();
		$accountId2 = Identifier::random();

		$repository->write(new AccountBalance($accountId1, Balance::zero(), Instant::of(1000)));
		$repository->write(new AccountBalance($accountId1, Balance::zero(), Instant::of(2000)));
		$repository->write(new AccountBalance($accountId2, Balance::zero(), Instant::of(3000)));

		$map = $repository->ofAccountId($accountId1, $accountId2)->toListMap(static fn(AccountBalance $b) => $b->accountId->toHex());

		self::assertCount(2, $map);
		self::assertCount(2, $map[$accountId1->toHex()]);
		self::assertCount(1, $map[$accountId2->toHex()]);
	}
}
