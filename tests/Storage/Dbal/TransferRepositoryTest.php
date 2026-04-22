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

use Castor\Ledgering\Amount;
use Castor\Ledgering\Code;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Infra\Database;
use Castor\Ledgering\Storage\InvalidResult;
use Castor\Ledgering\Time\Duration;
use Castor\Ledgering\Time\Instant;
use Castor\Ledgering\Transfer;
use Castor\Ledgering\TransferFlags;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class TransferRepositoryTest extends TestCase
{
	#[Test]
	public function it_writes_and_reads_transfer(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
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

		$externalId = Identifier::random();

		$transfer = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
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

		$groupId = Identifier::random();
		$externalId = Identifier::random();

		$transfer = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
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

		$groupId = Identifier::random();
		$debitAccountId = Identifier::random();
		$creditAccountId = Identifier::random();

		$transfer1 = new Transfer(
			id: Identifier::random(),
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$transfer2 = new Transfer(
			id: Identifier::random(),
			debitAccountId: $debitAccountId,
			creditAccountId: Identifier::random(),
			amount: Amount::of(2000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(2000),
		);

		// Transfer with different debit account
		$transfer3 = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: $creditAccountId,
			amount: Amount::of(3000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(3000),
		);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		$retrieved = $repository->ofExternalIdPrimary($groupId)->ofDebitAccount($debitAccountId)->toList();

		self::assertCount(2, $retrieved);
		self::assertTrue($retrieved[0]->debitAccountId->equals($debitAccountId));
		self::assertTrue($retrieved[1]->debitAccountId->equals($debitAccountId));
	}

	#[Test]
	public function it_filters_by_credit_account(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();
		$debitAccountId = Identifier::random();
		$creditAccountId = Identifier::random();

		$transfer1 = new Transfer(
			id: Identifier::random(),
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$transfer2 = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: $creditAccountId,
			amount: Amount::of(2000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(2000),
		);

		// Transfer with different credit account
		$transfer3 = new Transfer(
			id: Identifier::random(),
			debitAccountId: $debitAccountId,
			creditAccountId: Identifier::random(),
			amount: Amount::of(3000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(3000),
		);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		$retrieved = $repository->ofExternalIdPrimary($groupId)->ofCreditAccount($creditAccountId)->toList();

		self::assertCount(2, $retrieved);
		self::assertTrue($retrieved[0]->creditAccountId->equals($creditAccountId));
		self::assertTrue($retrieved[1]->creditAccountId->equals($creditAccountId));
	}

	#[Test]
	public function it_returns_empty_list_when_no_matching_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfers = $repository->ofExternalIdPrimary($groupId)->toList();

		self::assertSame([], $transfers);
	}

	#[Test]
	public function it_converts_to_list(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer2 = $this->createTransfer(Identifier::random(), groupId: $groupId);

		$repository->write($transfer1);
		$repository->write($transfer2);

		$transfers = $repository->ofExternalIdPrimary($groupId)->toList();

		self::assertCount(2, $transfers);
		self::assertTrue($transfers[0]->id->equals($transfer1->id));
		self::assertTrue($transfers[1]->id->equals($transfer2->id));
	}

	#[Test]
	public function it_converts_to_iterator(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer2 = $this->createTransfer(Identifier::random(), groupId: $groupId);

		$repository->write($transfer1);
		$repository->write($transfer2);

		$iterator = $repository->ofExternalIdPrimary($groupId)->toIterator();

		self::assertInstanceOf(\Iterator::class, $iterator);
		self::assertCount(2, \iterator_to_array($iterator));
	}

	#[Test]
	public function it_converts_to_map(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer2 = $this->createTransfer(Identifier::random(), groupId: $groupId);

		$repository->write($transfer1);
		$repository->write($transfer2);

		$map = $repository->ofExternalIdPrimary($groupId)->toMap(static fn(Transfer $t) => $t->id->toHex());

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

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(
			Identifier::random(),
			ledger: Code::of(1),
			groupId: $groupId,
		);
		$transfer2 = $this->createTransfer(
			Identifier::random(),
			ledger: Code::of(1),
			groupId: $groupId,
		);
		$transfer3 = $this->createTransfer(
			Identifier::random(),
			ledger: Code::of(2),
			groupId: $groupId,
		);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		/** @var array<string, array<Transfer>> $map */
		$map = $repository->ofExternalIdPrimary($groupId)->toListMap(static fn(Transfer $t) => (string) $t->ledger->value);

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

		$groupId = Identifier::random();
		$scoped = $repository->ofExternalIdPrimary($groupId);

		self::assertSame(0, $scoped->count());

		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));
		self::assertSame(1, $scoped->count());

		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));
		self::assertSame(2, $scoped->count());
	}

	#[Test]
	public function it_returns_first_transfer(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer2 = $this->createTransfer(Identifier::random(), groupId: $groupId);

		$repository->write($transfer1);
		$repository->write($transfer2);

		$first = $repository->ofExternalIdPrimary($groupId)->first();

		self::assertNotNull($first);
		self::assertTrue($first->id->equals($transfer1->id));
	}

	#[Test]
	public function it_returns_null_when_first_on_empty_result(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$first = $repository->ofExternalIdPrimary($groupId)->first();

		self::assertNull($first);
	}

	#[Test]
	public function it_returns_one_transfer(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$repository->write($transfer);

		$one = $repository->ofExternalIdPrimary($groupId)->one();

		self::assertTrue($one->id->equals($transfer->id));
	}

	#[Test]
	public function it_throws_when_one_on_empty_result(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Reader is empty');

		$repository->ofExternalIdPrimary($groupId)->one();
	}

	#[Test]
	public function it_throws_when_one_on_multiple_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));
		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Expected exactly one item, found 2');

		$repository->ofExternalIdPrimary($groupId)->one();
	}

	#[Test]
	public function it_picks_exact_number_of_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));
		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));

		$transfers = $repository->ofExternalIdPrimary($groupId)->pick(2);

		self::assertCount(2, $transfers);
	}

	#[Test]
	public function it_throws_when_pick_count_mismatch(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$repository->write($this->createTransfer(Identifier::random(), groupId: $groupId));

		$this->expectException(InvalidResult::class);
		$this->expectExceptionMessage('Expected exactly 2 items, found 1');

		$repository->ofExternalIdPrimary($groupId)->pick(2);
	}

	#[Test]
	public function it_slices_transfers_with_limit(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer2 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer3 = $this->createTransfer(Identifier::random(), groupId: $groupId);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		/** @var array<Transfer> $sliced */
		$sliced = $repository->ofExternalIdPrimary($groupId)->slice(0, 2)->toList();

		self::assertCount(2, $sliced);
		self::assertTrue($sliced[0]->id->equals($transfer1->id));
		self::assertTrue($sliced[1]->id->equals($transfer2->id));
	}

	#[Test]
	public function it_slices_transfers_with_limit_of_one(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		$transfer1 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer2 = $this->createTransfer(Identifier::random(), groupId: $groupId);
		$transfer3 = $this->createTransfer(Identifier::random(), groupId: $groupId);

		$repository->write($transfer1);
		$repository->write($transfer2);
		$repository->write($transfer3);

		/** @var array<Transfer> $sliced */
		$sliced = $repository->ofExternalIdPrimary($groupId)->slice(0, 1)->toList();

		self::assertCount(1, $sliced);
		self::assertTrue($sliced[0]->id->equals($transfer1->id));
	}

	#[Test]
	public function it_returns_expired_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();
		$pendingId = Identifier::random();

		// Create a pending transfer with 60 second timeout at t=1000
		$pending = new Transfer(
			id: $pendingId,
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check at t=1061 (1 second after expiration)
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1061))->toList();

		self::assertCount(1, $expired);
		self::assertTrue($expired[0]->id->equals($pendingId));
	}

	#[Test]
	public function it_does_not_return_transfers_before_timeout(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		// Create a pending transfer with 60 second timeout at t=1000
		$pending = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check at t=1059 (still within timeout)
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1059))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_transfers_with_zero_timeout(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		// Create a pending transfer with zero timeout
		$pending = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check at a much later time
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(999999))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_non_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();

		// Create a regular (non-pending) transfer with timeout
		$transfer = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(), // Not pending
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($transfer);

		// Check after timeout
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1061))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_posted_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();
		$pendingId = Identifier::random();

		// Create a pending transfer with timeout
		$pending = new Transfer(
			id: $pendingId,
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Create a POST_PENDING transfer that references the pending transfer
		$post = new Transfer(
			id: Identifier::random(),
			debitAccountId: $pending->debitAccountId,
			creditAccountId: $pending->creditAccountId,
			amount: Amount::of(1000),
			pendingId: $pendingId, // References the pending transfer
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::POST_PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1030),
		);

		$repository->write($post);

		// Check after timeout - should not return the posted pending transfer
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1061))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_does_not_return_voided_pending_transfers(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();
		$pendingId = Identifier::random();

		// Create a pending transfer with timeout
		$pending = new Transfer(
			id: $pendingId,
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Create a VOID_PENDING transfer that references the pending transfer
		$void = new Transfer(
			id: Identifier::random(),
			debitAccountId: $pending->debitAccountId,
			creditAccountId: $pending->creditAccountId,
			amount: Amount::of(0),
			pendingId: $pendingId, // References the pending transfer
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::VOID_PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1030),
		);

		$repository->write($void);

		// Check after timeout - should not return the voided pending transfer
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1061))->toList();

		self::assertCount(0, $expired);
	}

	#[Test]
	public function it_filters_multiple_transfers_correctly(): void
	{
		$connection = Database::connection();
		$repository = new TransferRepository($connection);

		$groupId = Identifier::random();
		$pending1Id = Identifier::random();
		$pending2Id = Identifier::random();
		$pending3Id = Identifier::random();
		$pending4Id = Identifier::random();

		$debitAccountId = Identifier::random();
		$creditAccountId = Identifier::random();

		// Pending transfer 1: Expired (timeout 60s at t=1000, checking at t=1061)
		$pending1 = new Transfer(
			id: $pending1Id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		// Pending transfer 2: Not expired yet (timeout 120s at t=1000, checking at t=1061)
		$pending2 = new Transfer(
			id: $pending2Id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(2000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(120),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		// Pending transfer 3: Expired but posted
		$pending3 = new Transfer(
			id: $pending3Id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(3000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		// Post for pending3
		$post3 = new Transfer(
			id: Identifier::random(),
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(3000),
			pendingId: $pending3Id,
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::POST_PENDING),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1030),
		);

		// Pending transfer 4: Expired (timeout 30s at t=1000, checking at t=1061)
		$pending4 = new Transfer(
			id: $pending4Id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: Amount::of(4000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(30),
			externalIdPrimary: $groupId,
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
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1061))->toList();

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

		$groupId = Identifier::random();

		// Create a pending transfer that hasn't expired yet
		$pending = new Transfer(
			id: Identifier::random(),
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::of(TransferFlags::PENDING),
			timeout: Duration::ofSeconds(60),
			externalIdPrimary: $groupId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);

		$repository->write($pending);

		// Check before expiration
		$expired = $repository->ofExternalIdPrimary($groupId)->expired(Instant::of(1000))->toList();

		self::assertCount(0, $expired);
	}

	private function createTransfer(Identifier $id, ?Code $ledger = null, ?Identifier $groupId = null): Transfer
	{
		return new Transfer(
			id: $id,
			debitAccountId: Identifier::random(),
			creditAccountId: Identifier::random(),
			amount: Amount::of(1000),
			pendingId: Identifier::zero(),
			ledger: $ledger ?? Code::of(1),
			code: Code::of(100),
			flags: TransferFlags::none(),
			timeout: Duration::zero(),
			externalIdPrimary: $groupId ?? Identifier::random(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			timestamp: Instant::of(1000),
		);
	}
}
