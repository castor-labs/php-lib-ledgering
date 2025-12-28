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
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Infra\Database;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\Dbal\AccountBalanceRepository;
use Castor\Ledgering\Storage\Dbal\AccountRepository;
use Castor\Ledgering\Storage\Dbal\TransactionalLedger;
use Castor\Ledgering\Storage\Dbal\TransferRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('db')]
final class TransactionalLedgerTest extends TestCase
{
	#[\Override]
	protected function setUp(): void
	{
		$connection = Database::connection();
		$connection->executeStatement('TRUNCATE TABLE ledgering_accounts CASCADE');
		$connection->executeStatement('TRUNCATE TABLE ledgering_transfers RESTART IDENTITY CASCADE');
		$connection->executeStatement('TRUNCATE TABLE ledgering_account_balances RESTART IDENTITY CASCADE');
	}

	#[Test]
	public function it_commits_successful_operations(): void
	{
		$connection = Database::connection();
		$accounts = new AccountRepository($connection);
		$transfers = new TransferRepository($connection);
		$balances = new AccountBalanceRepository($connection);

		$standardLedger = new StandardLedger($accounts, $transfers, $balances);
		$ledger = new TransactionalLedger($connection, $standardLedger);

		$accountId = Identifier::fromHex('11111111111111111111111111111111');

		$ledger->execute(
			CreateAccount::with(
				id: $accountId,
				ledger: 1,
				code: 100,
			),
		);

		// Verify account was created
		$account = $accounts->ofId($accountId)->first();
		self::assertNotNull($account);
		self::assertTrue($account->id->equals($accountId));
	}

	#[Test]
	public function it_rolls_back_on_failure(): void
	{
		$connection = Database::connection();
		$accounts = new AccountRepository($connection);
		$transfers = new TransferRepository($connection);
		$balances = new AccountBalanceRepository($connection);

		$standardLedger = new StandardLedger($accounts, $transfers, $balances);
		$ledger = new TransactionalLedger($connection, $standardLedger);

		$accountId = Identifier::fromHex('11111111111111111111111111111111');

		try {
			$ledger->execute(
				CreateAccount::with(
					id: $accountId,
					ledger: 1,
					code: 100,
				),
				// This will fail because we're trying to create the same account twice
				CreateAccount::with(
					id: $accountId,
					ledger: 1,
					code: 100,
				),
			);

			self::fail('Expected ConstraintViolation to be thrown');
		} catch (ConstraintViolation $e) {
			// Expected exception
		}

		// Verify account was NOT created (transaction rolled back)
		$account = $accounts->ofId($accountId)->first();
		self::assertNull($account);
	}

	#[Test]
	public function it_commits_multiple_operations_atomically(): void
	{
		$connection = Database::connection();
		$accounts = new AccountRepository($connection);
		$transfers = new TransferRepository($connection);
		$balances = new AccountBalanceRepository($connection);

		$standardLedger = new StandardLedger($accounts, $transfers, $balances);
		$ledger = new TransactionalLedger($connection, $standardLedger);

		$accountOne = Identifier::fromHex('11111111111111111111111111111111');
		$accountTwo = Identifier::fromHex('22222222222222222222222222222222');
		$transferId = Identifier::fromHex('33333333333333333333333333333333');

		$ledger->execute(
			CreateAccount::with(id: $accountOne, ledger: 1, code: 100),
			CreateAccount::with(id: $accountTwo, ledger: 1, code: 200),
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $accountOne,
				creditAccountId: $accountTwo,
				amount: Amount::of(1000),
				ledger: 1,
				code: 1,
			),
		);

		// Verify all operations succeeded
		$acc1 = $accounts->ofId($accountOne)->first();
		$acc2 = $accounts->ofId($accountTwo)->first();
		$transfer = $transfers->ofId($transferId)->first();

		self::assertNotNull($acc1);
		self::assertNotNull($acc2);
		self::assertNotNull($transfer);
		self::assertSame(1000, $acc1->balance->debitsPosted->value);
		self::assertSame(1000, $acc2->balance->creditsPosted->value);
	}
}
