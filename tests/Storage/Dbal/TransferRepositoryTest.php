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

namespace Castor\Ledgering\Tests\Storage\Dbal;

use Castor\Ledgering\Amount;
use Castor\Ledgering\Code;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Infra\Database;
use Castor\Ledgering\Storage\Dbal\TransferRepository;
use Castor\Ledgering\Storage\InvalidResult;
use Castor\Ledgering\Time\Duration;
use Castor\Ledgering\Time\Instant;
use Castor\Ledgering\Transfer;
use Castor\Ledgering\TransferFlags;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('db')]
final class TransferRepositoryTest extends TestCase
{
	#[\Override]
	protected function setUp(): void
	{
		$connection = Database::connection();
		$connection->executeStatement('TRUNCATE TABLE ledgering_transfers RESTART IDENTITY CASCADE');
	}

	#[Test]
	public function it_writes_and_reads_transfer(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer = new Transfer(
			id: Identifier::fromHex('0123456789abcdef0123456789abcdef'),
			debitAccountId: Identifier::fromHex('11111111111111111111111111111111'),
			creditAccountId: Identifier::fromHex('22222222222222222222222222222222'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1234567890, 123456789),
		);

		$repository->write($transfer);

		$retrieved = $repository->ofId($transfer->id)->first();

		self::assertNotNull($retrieved);
		self::assertTrue($retrieved->id->equals($transfer->id));
		self::assertTrue($retrieved->debitAccountId->equals($transfer->debitAccountId));
		self::assertTrue($retrieved->creditAccountId->equals($transfer->creditAccountId));
		self::assertSame(1000, $retrieved->amount->value);
		self::assertSame(1, $retrieved->ledger->value);
		self::assertSame(100, $retrieved->code->value);
		self::assertSame(1234567890, $retrieved->timestamp->seconds);
		self::assertSame(123456789, $retrieved->timestamp->nano);
	}

	#[Test]
	public function it_filters_by_external_id_primary(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$externalId = Identifier::fromHex('ffffffffffffffffffffffffffffffff');

		$transfer = new Transfer(
			id: Identifier::fromHex('0123456789abcdef0123456789abcdef'),
			debitAccountId: Identifier::fromHex('11111111111111111111111111111111'),
			creditAccountId: Identifier::fromHex('22222222222222222222222222222222'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $externalId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($transfer);

		$retrieved = $repository->ofExternalIdPrimary($externalId)->first();

		self::assertNotNull($retrieved);
		self::assertTrue($retrieved->externalIdPrimary->equals($externalId));
	}

	#[Test]
	public function it_filters_by_external_id_secondary(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$externalId = Identifier::fromHex('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');

		$transfer = new Transfer(
			id: Identifier::fromHex('0123456789abcdef0123456789abcdef'),
			debitAccountId: Identifier::fromHex('11111111111111111111111111111111'),
			creditAccountId: Identifier::fromHex('22222222222222222222222222222222'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: $externalId,
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($transfer);

		$retrieved = $repository->ofExternalIdSecondary($externalId)->first();

		self::assertNotNull($retrieved);
		self::assertTrue($retrieved->externalIdSecondary->equals($externalId));
	}

	#[Test]
	public function it_filters_by_debit_account(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$debitAccountId = Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
		$creditAccountId = Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');

		$transfer1 = new Transfer(
			id: Identifier::fromHex('11111111111111111111111111111111'),
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$transfer2 = new Transfer(
			id: Identifier::fromHex('22222222222222222222222222222222'),
			debitAccountId: $debitAccountId,
			creditAccountId: Identifier::fromHex('cccccccccccccccccccccccccccccccc'),
			amount: Amount::of(2000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(2000),
		);

		// Transfer with different debit account
		$transfer3 = new Transfer(
			id: Identifier::fromHex('33333333333333333333333333333333'),
			debitAccountId: Identifier::fromHex('dddddddddddddddddddddddddddddddd'),
			creditAccountId: $creditAccountId,
			amount: Amount::of(3000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(3000),
		);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		$retrieved = $repository->ofDebitAccount($debitAccountId)->toList();

		self::assertCount(2, $retrieved);
		self::assertTrue($retrieved[0]->debitAccountId->equals($debitAccountId));
		self::assertTrue($retrieved[1]->debitAccountId->equals($debitAccountId));
	}

	#[Test]
	public function it_filters_by_credit_account(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$debitAccountId = Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
		$creditAccountId = Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');

		$transfer1 = new Transfer(
			id: Identifier::fromHex('11111111111111111111111111111111'),
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$transfer2 = new Transfer(
			id: Identifier::fromHex('22222222222222222222222222222222'),
			debitAccountId: Identifier::fromHex('cccccccccccccccccccccccccccccccc'),
			creditAccountId: $creditAccountId,
			amount: Amount::of(2000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(2000),
		);

		// Transfer with different credit account
		$transfer3 = new Transfer(
			id: Identifier::fromHex('33333333333333333333333333333333'),
			debitAccountId: $debitAccountId,
			creditAccountId: Identifier::fromHex('dddddddddddddddddddddddddddddddd'),
			amount: Amount::of(3000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(3000),
		);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		$retrieved = $repository->ofCreditAccount($creditAccountId)->toList();

		self::assertCount(2, $retrieved);
		self::assertTrue($retrieved[0]->creditAccountId->equals($creditAccountId));
		self::assertTrue($retrieved[1]->creditAccountId->equals($creditAccountId));
	}

	#[Test]
	public function it_returns_empty_list_when_no_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfers = $repository->toList();

		self::assertSame([], $transfers);
	}

	#[Test]
	public function it_converts_to_list(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$transfer2 = $this->createTransfer(Identifier::fromHex('22222222222222222222222222222222'));

		$repository->write($transfer1);
		$repository->write($transfer2);

		$transfers = $repository->toList();

		self::assertCount(2, $transfers);
		self::assertTrue($transfers[0]->id->equals($transfer1->id));
		self::assertTrue($transfers[1]->id->equals($transfer2->id));
	}

	#[Test]
	public function it_converts_to_iterator(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$transfer2 = $this->createTransfer(Identifier::fromHex('22222222222222222222222222222222'));

		$repository->write($transfer1);
		$repository->write($transfer2);

		$iterator = $repository->toIterator();

		self::assertInstanceOf(\Iterator::class, $iterator);
		self::assertCount(2, \iterator_to_array($iterator));
	}

	#[Test]
	public function it_converts_to_map(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$transfer2 = $this->createTransfer(Identifier::fromHex('22222222222222222222222222222222'));

		$repository->write($transfer1);
		$repository->write($transfer2);

		$map = $repository->toMap(static fn(Transfer $t) => $t->id->toHex());

		self::assertCount(2, $map);
		self::assertArrayHasKey($transfer1->id->toHex(), $map);
		self::assertArrayHasKey($transfer2->id->toHex(), $map);
		self::assertTrue($map[$transfer1->id->toHex()]->id->equals($transfer1->id));
	}

	#[Test]
	public function it_converts_to_list_map(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(
			Identifier::fromHex('11111111111111111111111111111111'),
			ledger: Code::of(1),
		);
		$transfer2 = $this->createTransfer(
			Identifier::fromHex('22222222222222222222222222222222'),
			ledger: Code::of(1),
		);
		$transfer3 = $this->createTransfer(
			Identifier::fromHex('33333333333333333333333333333333'),
			ledger: Code::of(2),
		);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		/** @var array<string, array<Transfer>> $map */
		$map = $repository->toListMap(static fn(Transfer $t) => (string) $t->ledger->value);

		self::assertCount(2, $map);
		self::assertArrayHasKey('1', $map);
		self::assertArrayHasKey('2', $map);
		/** @psalm-suppress InvalidArrayOffset */
		self::assertCount(2, $map['1']);
		/** @psalm-suppress InvalidArrayOffset */
		self::assertCount(1, $map['2']);
	}

	#[Test]
	public function it_counts_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		self::assertSame(0, $repository->count());

		$repository->write($this->createTransfer(Identifier::fromHex('11111111111111111111111111111111')));
		self::assertSame(1, $repository->count());

		$repository->write($this->createTransfer(Identifier::fromHex('22222222222222222222222222222222')));
		self::assertSame(2, $repository->count());
	}

	#[Test]
	public function it_returns_first_transfer(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$transfer2 = $this->createTransfer(Identifier::fromHex('22222222222222222222222222222222'));

		$repository->write($transfer1);
		$repository->write($transfer2);

		$first = $repository->first();

		self::assertNotNull($first);
		self::assertTrue($first->id->equals($transfer1->id));
	}

	#[Test]
	public function it_returns_null_when_first_on_empty_repository(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$first = $repository->first();

		self::assertNull($first);
	}

	#[Test]
	public function it_returns_one_transfer(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$repository->write($transfer);

		$one = $repository->one();

		self::assertTrue($one->id->equals($transfer->id));
	}

	#[Test]
	public function it_throws_when_one_on_empty_repository(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Reader is empty');

		$repository->one();
	}

	#[Test]
	public function it_throws_when_one_on_multiple_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$repository->write($this->createTransfer(Identifier::fromHex('11111111111111111111111111111111')));
		$repository->write($this->createTransfer(Identifier::fromHex('22222222222222222222222222222222')));

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Expected exactly one item, found 2');

		$repository->one();
	}

	#[Test]
	public function it_picks_exact_number_of_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$repository->write($this->createTransfer(Identifier::fromHex('11111111111111111111111111111111')));
		$repository->write($this->createTransfer(Identifier::fromHex('22222222222222222222222222222222')));

		$transfers = $repository->pick(2);

		self::assertCount(2, $transfers);
	}

	#[Test]
	public function it_throws_when_pick_count_mismatch(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$repository->write($this->createTransfer(Identifier::fromHex('11111111111111111111111111111111')));

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Expected exactly 2 items, found 1');

		$repository->pick(2);
	}

	#[Test]
	public function it_slices_transfers_with_offset(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$transfer2 = $this->createTransfer(Identifier::fromHex('22222222222222222222222222222222'));
		$transfer3 = $this->createTransfer(Identifier::fromHex('33333333333333333333333333333333'));

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		/** @var array<Transfer> $sliced */
		$sliced = $repository->slice(1)->toList();

		self::assertCount(2, $sliced);
		self::assertTrue($sliced[0]->id->equals($transfer2->id));
		self::assertTrue($sliced[1]->id->equals($transfer3->id));
	}

	#[Test]
	public function it_slices_transfers_with_offset_and_limit(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$transfer1 = $this->createTransfer(Identifier::fromHex('11111111111111111111111111111111'));
		$transfer2 = $this->createTransfer(Identifier::fromHex('22222222222222222222222222222222'));
		$transfer3 = $this->createTransfer(Identifier::fromHex('33333333333333333333333333333333'));

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		/** @var array<Transfer> $sliced */
		$sliced = $repository->slice(1, 1)->toList();

		self::assertCount(1, $sliced);
		self::assertTrue($sliced[0]->id->equals($transfer2->id));
	}

	#[Test]
	public function it_returns_expired_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$pendingId = Identifier::fromHex('11111111111111111111111111111111');

		// Create a pending transfer with 60 second timeout at t=1000
		$pending = new Transfer(
			id: $pendingId,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check at t=1061 (1 second after expiration)
		$expired = $repository->expired(Instant::of(1061))->toList();

		self::assertCount(1, $expired);
		self::assertTrue($expired[0]->id->equals($pendingId));
	}

	#[Test]
	public function it_does_not_return_transfers_before_timeout(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		// Create a pending transfer with 60 second timeout at t=1000
		$pending = new Transfer(
			id: Identifier::fromHex('11111111111111111111111111111111'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check at t=1059 (still within timeout)
		$expired = $repository->expired(Instant::of(1059))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_transfers_with_zero_timeout(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		// Create a pending transfer with zero timeout
		$pending = new Transfer(
			id: Identifier::fromHex('11111111111111111111111111111111'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check at a much later time
		$expired = $repository->expired(Instant::of(999999))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_non_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		// Create a regular (non-pending) transfer with timeout
		$transfer = new Transfer(
			id: Identifier::fromHex('11111111111111111111111111111111'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(), // Not pending
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($transfer);

		// Check after timeout
		$expired = $repository->expired(Instant::of(1061))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_posted_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$pendingId = Identifier::fromHex('11111111111111111111111111111111');

		// Create a pending transfer with timeout
		$pending = new Transfer(
			id: $pendingId,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Create a POST_PENDING transfer that references the pending transfer
		$post = new Transfer(
			id: Identifier::fromHex('22222222222222222222222222222222'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: $pendingId, // References the pending transfer
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::POST_PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1030),
		);

		$repository->write($post);

		// Check after timeout - should not return the posted pending transfer
		$expired = $repository->expired(Instant::of(1061))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_voided_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$pendingId = Identifier::fromHex('11111111111111111111111111111111');

		// Create a pending transfer with timeout
		$pending = new Transfer(
			id: $pendingId,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Create a VOID_PENDING transfer that references the pending transfer
		$void = new Transfer(
			id: Identifier::fromHex('22222222222222222222222222222222'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(0),
			pendingId: $pendingId, // References the pending transfer
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::VOID_PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1030),
		);

		$repository->write($void);

		// Check after timeout - should not return the voided pending transfer
		$expired = $repository->expired(Instant::of(1061))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_filters_multiple_transfers_correctly(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$pending1Id = Identifier::fromHex('11111111111111111111111111111111');
		$pending2Id = Identifier::fromHex('22222222222222222222222222222222');
		$pending3Id = Identifier::fromHex('33333333333333333333333333333333');
		$pending4Id = Identifier::fromHex('44444444444444444444444444444444');

		// Pending transfer 1: Expired (timeout 60s at t=1000, checking at t=1061)
		$pending1 = new Transfer(
			id: $pending1Id,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		// Pending transfer 2: Not expired yet (timeout 120s at t=1000, checking at t=1061)
		$pending2 = new Transfer(
			id: $pending2Id,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(2000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(120),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		// Pending transfer 3: Expired but posted
		$pending3 = new Transfer(
			id: $pending3Id,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(3000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		// Post for pending3
		$post3 = new Transfer(
			id: Identifier::fromHex('55555555555555555555555555555555'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(3000),
			pendingId: $pending3Id,
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::POST_PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1030),
		);

		// Pending transfer 4: Expired (timeout 30s at t=1000, checking at t=1061)
		$pending4 = new Transfer(
			id: $pending4Id,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(4000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(30),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending1);
		$repository->write($pending2);
		$repository->write($pending3);
		$repository->write($post3);
		$repository->write($pending4);

		// Check at t=1061
		$expired = $repository->expired(Instant::of(1061))->toList();

		// Should return only pending1 and pending4 (both expired and not posted/voided)
		self::assertCount(2, $expired);

		$expiredIds = \array_map(static fn($t) => $t->id->toHex(), $expired);
		self::assertContains($pending1Id->toHex(), $expiredIds);
		self::assertContains($pending4Id->toHex(), $expiredIds);
	}

	#[Test]
	public function it_returns_empty_when_no_expired_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		// Create a pending transfer that hasn't expired yet
		$pending = new Transfer(
			id: Identifier::fromHex('11111111111111111111111111111111'),
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check before expiration
		$expired = $repository->expired(Instant::of(1000))->toList();

		self::assertCount(0, $expired);
	}

	private function createTransfer(Identifier $id, ?Code $ledger = null): Transfer
	{
		return new Transfer(
			id: $id,
			debitAccountId: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
			creditAccountId: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: $ledger ?? Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);
	}
}
