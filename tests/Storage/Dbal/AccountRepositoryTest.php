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

use Castor\Ledgering\Account;
use Castor\Ledgering\AccountFlags;
use Castor\Ledgering\Amount;
use Castor\Ledgering\Balance;
use Castor\Ledgering\Code;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Infra\Database;
use Castor\Ledgering\Time\Instant;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class AccountRepositoryTest extends TestCase
{
	#[\Override]
	protected function setUp(): void
	{
		$connection = Database::connection();

		// Clean up accounts table before each test
		$connection->executeStatement('TRUNCATE TABLE ledgering_accounts CASCADE');
	}

	#[Test]
	public function it_writes_and_reads_account(): void
	{
		$connection = Database::connection();
		$repository = new AccountRepository($connection);

		$account = new Account(
			id: Identifier::fromHex('0123456789abcdef0123456789abcdef'),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: AccountFlags::none(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			balance: Balance::zero(),
			timestamp: Instant::of(1234567890, 123456789),
		);

		$repository->write($account);

		$retrieved = $repository->ofId($account->id)->first();

		self::assertNotNull($retrieved);
		self::assertTrue($retrieved->id->equals($account->id));
		self::assertSame(1, $retrieved->ledger->value);
		self::assertSame(100, $retrieved->code->value);
		self::assertSame(1234567890, $retrieved->timestamp->seconds);
		self::assertSame(123456789, $retrieved->timestamp->nano);
	}

	#[Test]
	public function it_updates_existing_account(): void
	{
		$connection = Database::connection();
		$repository = new AccountRepository($connection);

		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');

		// Write initial account
		$account1 = new Account(
			id: $id,
			ledger: Code::of(1),
			code: Code::of(100),
			flags: AccountFlags::none(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			balance: Balance::zero(),
			timestamp: Instant::of(1000),
		);

		$repository->write($account1);

		// Update with new balance
		$account2 = new Account(
			id: $id,
			ledger: Code::of(1),
			code: Code::of(100),
			flags: AccountFlags::none(),
			externalIdPrimary: Identifier::zero(),
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			balance: new Balance(
				debitsPosted: Amount::of(1000),
				creditsPosted: Amount::of(500),
				debitsPending: Amount::of(200),
				creditsPending: Amount::of(100),
			),
			timestamp: Instant::of(2000),
		);

		$repository->write($account2);

		// Should only have one account
		self::assertSame(1, $repository->count());

		$retrieved = $repository->ofId($id)->first();
		self::assertNotNull($retrieved);
		self::assertSame(1000, $retrieved->balance->debitsPosted->value);
		self::assertSame(500, $retrieved->balance->creditsPosted->value);
		self::assertSame(2000, $retrieved->timestamp->seconds);
	}

	#[Test]
	public function it_filters_by_external_id_primary(): void
	{
		$connection = Database::connection();
		$repository = new AccountRepository($connection);

		$externalId = Identifier::fromHex('ffffffffffffffffffffffffffffffff');

		$account = new Account(
			id: Identifier::fromHex('0123456789abcdef0123456789abcdef'),
			ledger: Code::of(1),
			code: Code::of(100),
			flags: AccountFlags::none(),
			externalIdPrimary: $externalId,
			externalIdSecondary: Identifier::zero(),
			externalCodePrimary: Code::of(1),
			balance: Balance::zero(),
			timestamp: Instant::of(1000),
		);

		$repository->write($account);

		$retrieved = $repository->ofExternalIdPrimary($externalId)->first();

		self::assertNotNull($retrieved);
		self::assertTrue($retrieved->externalIdPrimary->equals($externalId));
	}
}
