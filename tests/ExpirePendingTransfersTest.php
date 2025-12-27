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

namespace Castor\Ledgering\Tests;

use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\ErrorCode;
use Castor\Ledgering\ExpirePendingTransfers;
use Castor\Ledgering\FixedClock;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use Castor\Ledgering\Time\Duration;
use Castor\Ledgering\Time\Instant;
use Castor\Ledgering\TransferFlags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpirePendingTransfersTest extends TestCase
{
	#[Test]
	public function it_creates_command_with_specific_instant(): void
	{
		$instant = Instant::of(1000);
		$command = ExpirePendingTransfers::asOf($instant);

		self::assertTrue($command->asOf->equals($instant));
	}

	#[Test]
	public function it_creates_command_with_current_time(): void
	{
		$before = Instant::now();
		$command = ExpirePendingTransfers::asOf(Instant::now());
		$after = Instant::now();

		// Command's instant should be between before and after
		self::assertTrue($command->asOf->compare($before) >= 0);
		self::assertTrue($command->asOf->compare($after) <= 0);
	}

	#[Test]
	public function it_expires_pending_transfers_with_timeout(): void
	{
		$accounts = new AccountCollection();
		$transfers = new TransferCollection();
		$balances = new AccountBalanceCollection();
		$clock = FixedClock::at(1000);

		$ledger = new StandardLedger($accounts, $transfers, $balances, $clock);

		$accountOne = Identifier::fromHex('11111111111111111111111111111111');
		$accountTwo = Identifier::fromHex('22222222222222222222222222222222');
		$pendingId = Identifier::fromHex('33333333333333333333333333333333');

		// Create accounts
		$ledger->execute(
			CreateAccount::with(id: $accountOne, ledger: 1, code: 100),
			CreateAccount::with(id: $accountTwo, ledger: 1, code: 200),
		);

		// Create pending transfer with 60 second timeout at t=1000
		$ledger->execute(
			CreateTransfer::with(
				id: $pendingId,
				debitAccountId: $accountOne,
				creditAccountId: $accountTwo,
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
				timeout: Duration::ofSeconds(60),
			),
		);

		// Verify pending balances
		$acc1 = $accounts->ofId($accountOne)->one();
		$acc2 = $accounts->ofId($accountTwo)->one();
		self::assertSame(1000, $acc1->balance->debitsPending->value);
		self::assertSame(1000, $acc2->balance->creditsPending->value);

		// Expire transfers at t=1061 (1 second after timeout)
		$ledger->execute(
			ExpirePendingTransfers::asOf(Instant::of(1061)),
		);

		// Verify pending balances are cleared
		$acc1 = $accounts->ofId($accountOne)->one();
		$acc2 = $accounts->ofId($accountTwo)->one();
		self::assertSame(0, $acc1->balance->debitsPending->value);
		self::assertSame(0, $acc2->balance->creditsPending->value);
	}

	#[Test]
	public function it_does_not_expire_transfers_before_timeout(): void
	{
		$accounts = new AccountCollection();
		$transfers = new TransferCollection();
		$balances = new AccountBalanceCollection();
		$clock = FixedClock::at(1000);

		$ledger = new StandardLedger($accounts, $transfers, $balances, $clock);

		$accountOne = Identifier::fromHex('11111111111111111111111111111111');
		$accountTwo = Identifier::fromHex('22222222222222222222222222222222');
		$pendingId = Identifier::fromHex('33333333333333333333333333333333');

		// Create accounts and pending transfer
		$ledger->execute(
			CreateAccount::with(id: $accountOne, ledger: 1, code: 100),
			CreateAccount::with(id: $accountTwo, ledger: 1, code: 200),
			CreateTransfer::with(
				id: $pendingId,
				debitAccountId: $accountOne,
				creditAccountId: $accountTwo,
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
				timeout: Duration::ofSeconds(60),
			),
		);

		// Try to expire at t=1059 (still within timeout)
		$ledger->execute(
			ExpirePendingTransfers::asOf(Instant::of(1059)),
		);

		// Verify pending balances are still there
		$acc1 = $accounts->ofId($accountOne)->one();
		$acc2 = $accounts->ofId($accountTwo)->one();
		self::assertSame(1000, $acc1->balance->debitsPending->value);
		self::assertSame(1000, $acc2->balance->creditsPending->value);
	}

	#[Test]
	public function it_does_not_expire_transfers_with_zero_timeout(): void
	{
		$accounts = new AccountCollection();
		$transfers = new TransferCollection();
		$balances = new AccountBalanceCollection();
		$clock = FixedClock::at(1000);

		$ledger = new StandardLedger($accounts, $transfers, $balances, $clock);

		$accountOne = Identifier::fromHex('11111111111111111111111111111111');
		$accountTwo = Identifier::fromHex('22222222222222222222222222222222');
		$pendingId = Identifier::fromHex('33333333333333333333333333333333');

		// Create accounts and pending transfer with zero timeout
		$ledger->execute(
			CreateAccount::with(id: $accountOne, ledger: 1, code: 100),
			CreateAccount::with(id: $accountTwo, ledger: 1, code: 200),
			CreateTransfer::with(
				id: $pendingId,
				debitAccountId: $accountOne,
				creditAccountId: $accountTwo,
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
				// No timeout specified (defaults to zero)
			),
		);

		// Try to expire at a much later time
		$ledger->execute(
			ExpirePendingTransfers::asOf(Instant::of(999999)),
		);

		// Verify pending balances are still there (zero timeout means never expires)
		$acc1 = $accounts->ofId($accountOne)->one();
		$acc2 = $accounts->ofId($accountTwo)->one();
		self::assertSame(1000, $acc1->balance->debitsPending->value);
		self::assertSame(1000, $acc2->balance->creditsPending->value);
	}

	#[Test]
	public function it_prevents_posting_expired_transfer(): void
	{
		$accounts = new AccountCollection();
		$transfers = new TransferCollection();
		$balances = new AccountBalanceCollection();
		$clock = FixedClock::at(1000);

		$ledger = new StandardLedger($accounts, $transfers, $balances, $clock);

		$accountOne = Identifier::fromHex('11111111111111111111111111111111');
		$accountTwo = Identifier::fromHex('22222222222222222222222222222222');
		$pendingId = Identifier::fromHex('33333333333333333333333333333333');

		// Create accounts and pending transfer
		$ledger->execute(
			CreateAccount::with(id: $accountOne, ledger: 1, code: 100),
			CreateAccount::with(id: $accountTwo, ledger: 1, code: 200),
			CreateTransfer::with(
				id: $pendingId,
				debitAccountId: $accountOne,
				creditAccountId: $accountTwo,
				amount: 1000,
				ledger: 1,
				code: 1,
				flags: TransferFlags::PENDING,
				timeout: Duration::ofSeconds(60),
			),
		);

		// Advance clock past expiration
		$clock->setNow(Instant::of(1061));

		// Try to post the expired transfer
		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::PendingTransferExpired->value);

		$ledger->execute(
			CreateTransfer::with(
				id: Identifier::random(),
				debitAccountId: $accountOne,
				creditAccountId: $accountTwo,
				amount: 0,
				ledger: 1,
				code: 1,
				flags: TransferFlags::POST_PENDING,
				pendingId: $pendingId,
			),
		);
	}
}
